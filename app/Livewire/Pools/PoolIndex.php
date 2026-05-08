<?php

namespace App\Livewire\Pools;

use App\Enums\PoolMemberStatus;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\PoolRanking;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PoolIndex extends Component
{
    public string $invite_code = '';
    public string $invite_sector = '';
    /** @var string[] */
    public array $invite_sectors = [];

    public function updatedInviteCode(string $value): void
    {
        $code = strtoupper(trim($value));

        if (strlen($code) !== 8) {
            $this->invite_sectors = [];
            $this->invite_sector = '';
            return;
        }

        $pool = Pool::query()->where('invite_code', $code)->where('status', 'active')->first(['id', 'sectors']);
        $this->invite_sectors = $pool && is_array($pool->sectors) ? array_values($pool->sectors) : [];

        if (! in_array($this->invite_sector, $this->invite_sectors, true)) {
            $this->invite_sector = '';
        }
    }

    public function joinByInviteCode(): void
    {
        $data = $this->validate([
            'invite_code' => ['required', 'string', 'size:8'],
            'invite_sector' => ['nullable', 'string', 'max:80'],
        ]);

        $code = strtoupper(trim($data['invite_code']));
        $pool = Pool::query()->where('invite_code', $code)->where('status', 'active')->first();

        if (! $pool) {
            $this->addError('invite_code', 'Codigo de convite invalido.');
            $this->invite_sectors = [];
            $this->invite_sector = '';
            return;
        }

        $this->invite_sectors = is_array($pool->sectors) ? array_values($pool->sectors) : [];

        if (! empty($this->invite_sectors) && ! in_array($data['invite_sector'] ?? '', $this->invite_sectors, true)) {
            $this->addError('invite_sector', 'Selecione um setor valido para entrar neste bolao.');
            return;
        }

        $userId = (int) Auth::id();
        $member = PoolMember::query()
            ->where('pool_id', $pool->id)
            ->where('user_id', $userId)
            ->first();

        if ($member) {
            session()->flash('status', 'Voce ja faz parte deste bolao.');
            $this->redirectRoute('pools.show', ['pool' => $pool->slug], navigate: true);
            return;
        }

        $pool->members()->create([
            'user_id' => $userId,
            'role' => 'member',
            'sector' => ! empty($this->invite_sectors) ? ($data['invite_sector'] ?: null) : null,
            'status' => PoolMemberStatus::Pending->value,
        ]);

        session()->flash('status', 'Solicitacao enviada. Aguarde liberacao do gestor do bolao.');
        $this->invite_code = '';
        $this->invite_sector = '';
        $this->invite_sectors = [];
    }

    public function requestPublicEntry(int $poolId): void
    {
        $userId = (int) Auth::id();

        $pool = Pool::query()
            ->whereKey($poolId)
            ->where('visibility', 'public')
            ->where('status', 'active')
            ->first();

        if (! $pool) {
            session()->flash('status', 'Este bolão público não está disponível no momento.');
            return;
        }

        $member = PoolMember::query()
            ->where('pool_id', $pool->id)
            ->where('user_id', $userId)
            ->first();

        if ($member) {
            session()->flash('status', 'Você já possui participação solicitada/ativa neste bolão.');
            if ($member->status === PoolMemberStatus::Active->value) {
                $this->redirectRoute('pools.show', ['pool' => $pool->slug], navigate: true);
            }
            return;
        }

        $pool->members()->create([
            'user_id' => $userId,
            'role' => 'member',
            'sector' => null,
            'status' => PoolMemberStatus::Pending->value,
        ]);

        session()->flash('status', 'Solicitação enviada. Aguarde aprovação do gestor do bolão.');
    }

    public function render()
    {
        $userId = (int) Auth::id();

        $myPools = PoolMember::query()
            ->where('user_id', $userId)
            ->with('pool:id,name,slug,status,visibility,invite_code')
            ->latest('id')
            ->get();

        $myRankings = PoolRanking::query()
            ->where('user_id', $userId)
            ->whereIn('pool_id', $myPools->pluck('pool_id')->all())
            ->get(['pool_id', 'position', 'points_total'])
            ->keyBy('pool_id');

        $publicPools = Pool::query()
            ->where('visibility', 'public')
            ->where('status', 'active')
            ->whereDoesntHave('members', fn ($q) => $q->where('user_id', $userId))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'slug', 'description', 'status', 'visibility']);

        return view('livewire.pools.poolindex', compact('myPools', 'myRankings', 'publicPools'));
    }
}
