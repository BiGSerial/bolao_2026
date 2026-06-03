<?php

namespace App\Livewire\Pools;

use App\Enums\PoolMemberRole;
use App\Models\Pool;
use App\Models\PoolInvite;
use App\Models\PoolMember;
use App\Notifications\PoolInviteNotification;
use App\Services\Email\EmailDispatchGuard;
use App\Services\Pools\PoolMembershipService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class PoolMembers extends Component
{
    public Pool $pool;
    public string $filterStatus = '';
    public string $invite_email = '';
    public string $invite_sector = '';

    public function mount(Pool $pool): void
    {
        $this->pool = $pool;
        $this->assertManager();
    }

    #[On('echo-private:pool.{pool.id},MembersUpdated')]
    public function refreshMembers(): void
    {
        $this->pool->refresh();
    }

    public function activateMember(int $memberId, PoolMembershipService $service): void
    {
        $this->handleMembershipAction($memberId, fn (PoolMember $member) => $service->activate($this->pool, $member, Auth::user()));
    }

    public function deactivateMember(int $memberId, PoolMembershipService $service): void
    {
        $this->handleMembershipAction($memberId, fn (PoolMember $member) => $service->deactivate($this->pool, $member, Auth::user()));
    }

    public function removeMember(int $memberId, PoolMembershipService $service): void
    {
        $this->handleMembershipAction($memberId, fn (PoolMember $member) => $service->remove($this->pool, $member, Auth::user()));
    }

    public function blockMember(int $memberId, PoolMembershipService $service): void
    {
        $this->handleMembershipAction($memberId, fn (PoolMember $member) => $service->block($this->pool, $member, Auth::user()));
    }

    public function promoteManager(int $memberId, PoolMembershipService $service): void
    {
        $this->handleMembershipAction($memberId, fn (PoolMember $member) => $service->promoteToManager($this->pool, $member, Auth::user()));
    }

    public function demoteMember(int $memberId, PoolMembershipService $service): void
    {
        $this->handleMembershipAction($memberId, fn (PoolMember $member) => $service->demoteToMember($this->pool, $member, Auth::user()));
    }

    public function updateSector(int $memberId, string $sector): void
    {
        $this->assertManager();
        $member = $this->findMember($memberId);
        $allowedSectors = is_array($this->pool->sectors) ? $this->pool->sectors : [];
        $normalizedSector = trim($sector);

        if ($normalizedSector !== '' && ! in_array($normalizedSector, $allowedSectors, true)) {
            session()->flash('error', 'Setor invalido para este bolao.');
            return;
        }

        $member->update(['sector' => $normalizedSector !== '' ? $normalizedSector : null]);
        session()->flash('status', 'Setor atualizado com sucesso.');
    }

    public function sendInvite(EmailDispatchGuard $emailGuard): void
    {
        $this->assertManager();

        $data = $this->validate([
            'invite_email' => ['required', 'email', 'max:255'],
            'invite_sector' => ['nullable', 'string', 'max:80'],
        ], [
            'invite_email.required' => 'Informe o e-mail do convidado.',
            'invite_email.email' => 'Informe um e-mail válido.',
        ]);

        $allowedSectors = is_array($this->pool->sectors) ? $this->pool->sectors : [];
        $sector = trim((string) ($data['invite_sector'] ?? ''));
        if ($sector !== '' && ! in_array($sector, $allowedSectors, true)) {
            session()->flash('error', 'Setor inválido para este bolão.');
            return;
        }

        $recipientEmail = mb_strtolower(trim($data['invite_email']));
        $senderLimit = (int) config('email-limits.limits.pool_invite_sender_hour', 20);
        $recipientLimit = (int) config('email-limits.limits.pool_invite_recipient_day', 3);

        if (! $emailGuard->attempt('pool_invite_sender', (string) Auth::id(), $senderLimit, 3600)) {
            session()->flash('error', 'Limite de convites por hora atingido ou cota mensal de e-mails esgotada.');
            return;
        }

        if (! $emailGuard->attempt('pool_invite_recipient', $recipientEmail, $recipientLimit, 86400)) {
            session()->flash('error', 'Este e-mail já recebeu muitos convites recentemente. Tente novamente mais tarde.');
            return;
        }

        $invite = PoolInvite::create([
            'pool_id' => $this->pool->id,
            'invited_by' => (int) Auth::id(),
            'email' => $recipientEmail,
            'sector' => $sector !== '' ? $sector : null,
            'token' => Str::random(48),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $invite->load('pool');
        Notification::route('mail', $invite->email)->notify(new PoolInviteNotification($invite));

        $this->invite_email = '';
        $this->invite_sector = '';
        session()->flash('status', 'Convite enviado com sucesso.');
    }

    private function findMember(int $memberId): PoolMember
    {
        return $this->pool->members()->whereKey($memberId)->firstOrFail();
    }

    private function handleMembershipAction(int $memberId, callable $callback): void
    {
        $this->assertManager();
        $member = $this->findMember($memberId);

        try {
            $callback($member);
            session()->flash('status', 'Ação executada com sucesso.');
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    private function assertManager(): PoolMember
    {
        $isAdmin = (bool) Auth::user()?->is_admin;
        abort_if(! $isAdmin && $this->pool->status !== 'active', 403);

        $member = $this->pool->members()->where('user_id', Auth::id())->first();
        abort_if(! $member, 403);
        abort_unless(in_array($member->role, [PoolMemberRole::Owner->value, PoolMemberRole::Manager->value], true), 403);
        return $member;
    }

    public function render()
    {
        $query = $this->pool->members()
            ->with('user:id,name,display_name,area,email')
            ->orderByRaw("case role when 'owner' then 0 when 'manager' then 1 else 2 end")
            ->orderBy('id');

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        $members = $query->get();

        $counts = $this->pool->members()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $pendingMembers = $this->pool->members()
            ->with('user:id,name,email')
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();

        return view('livewire.pools.poolmembers', compact('members', 'counts', 'pendingMembers'));
    }
}
