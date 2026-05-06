<?php

namespace App\Livewire\Pools;

use App\Enums\PoolMemberRole;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Services\Pools\PoolMembershipService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PoolMembers extends Component
{
    public Pool $pool;
    public string $filterStatus = '';

    public function mount(Pool $pool): void
    {
        $this->pool = $pool;
        $this->assertManager();
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
        $member = $this->pool->members()->where('user_id', Auth::id())->first();
        abort_if(! $member, 403);
        abort_unless(in_array($member->role, [PoolMemberRole::Owner->value, PoolMemberRole::Manager->value], true), 403);
        return $member;
    }

    public function render()
    {
        $query = $this->pool->members()
            ->with('user:id,name,area,email,phone')
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

        return view('livewire.pools.poolmembers', compact('members', 'counts'));
    }
}
