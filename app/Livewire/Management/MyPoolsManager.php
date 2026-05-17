<?php

namespace App\Livewire\Management;

use App\Models\Pool;
use App\Models\PoolMember;
use App\Services\Pools\PoolMembershipService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class MyPoolsManager extends Component
{
    public ?int $selectedPoolId = null;
    public string $filterStatus = '';
    public string $search = '';

    public function mount(): void
    {
        $requestedPoolId = request()->integer('pool');
        if ($requestedPoolId > 0 && $this->myPools()->whereKey($requestedPoolId)->exists()) {
            $this->selectedPoolId = $requestedPoolId;
            return;
        }

        $first = $this->myPools()->first();
        if ($first) {
            $this->selectedPoolId = $first->id;
        }
    }

    public function selectPool(int $poolId): void
    {
        $exists = $this->myPools()->whereKey($poolId)->exists();
        if (! $exists) {
            return;
        }

        $this->selectedPoolId = $poolId;
        $this->filterStatus = '';
        $this->search = '';
    }

    #[On('echo-private:pool.{selectedPoolId},MembersUpdated')]
    public function refreshManagedPoolData(): void
    {
        // Evento recebido via Reverb; re-render já consulta os dados atualizados.
    }

    public function activateMember(int $memberId, PoolMembershipService $service): void
    {
        $this->memberAction($memberId, fn ($pool, $member) => $service->activate($pool, $member, Auth::user()));
    }

    public function deactivateMember(int $memberId, PoolMembershipService $service): void
    {
        $this->memberAction($memberId, fn ($pool, $member) => $service->deactivate($pool, $member, Auth::user()));
    }

    public function removeMember(int $memberId, PoolMembershipService $service): void
    {
        $this->memberAction($memberId, fn ($pool, $member) => $service->remove($pool, $member, Auth::user()));
    }

    public function blockMember(int $memberId, PoolMembershipService $service): void
    {
        $this->memberAction($memberId, fn ($pool, $member) => $service->block($pool, $member, Auth::user()));
    }

    public function promoteManager(int $memberId, PoolMembershipService $service): void
    {
        $this->memberAction($memberId, fn ($pool, $member) => $service->promoteToManager($pool, $member, Auth::user()));
    }

    public function demoteMember(int $memberId, PoolMembershipService $service): void
    {
        $this->memberAction($memberId, fn ($pool, $member) => $service->demoteToMember($pool, $member, Auth::user()));
    }

    public function transferOwnership(int $memberId, PoolMembershipService $service): void
    {
        $this->memberAction($memberId, fn ($pool, $member) => $service->transferOwnership($pool, $member, Auth::user()));
    }

    public function updateSector(int $memberId, string $sector): void
    {
        $pool = $this->selectedPool();
        abort_if(! $pool, 403);
        $this->assertManager($pool);

        $normalizedSector = preg_replace('/\s+/', ' ', trim($sector)) ?? '';
        $allowedSectors = collect($pool->sectors ?? [])
            ->map(fn ($item) => preg_replace('/\s+/', ' ', trim((string) $item)) ?? '')
            ->filter(fn (string $item) => $item !== '')
            ->values();

        if ($normalizedSector !== '' && ! $allowedSectors->contains($normalizedSector)) {
            abort(422, 'Setor inválido para este bolão.');
        }

        $member = $pool->members()->whereKey($memberId)->firstOrFail();
        $member->update(['sector' => $normalizedSector !== '' ? $normalizedSector : null]);
    }

    private function memberAction(int $memberId, callable $callback): void
    {
        $pool = $this->selectedPool();
        abort_if(! $pool, 403);
        $this->assertManager($pool);

        $member = $pool->members()->whereKey($memberId)->firstOrFail();

        try {
            $callback($pool, $member);
            $this->dispatch('action-done');
        } catch (DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    private function assertManager(Pool $pool): void
    {
        $member = $pool->members()->where('user_id', Auth::id())->first();
        abort_if(! $member, 403);
        abort_unless(in_array($member->role, ['owner', 'manager'], true), 403);
    }

    private function myPools()
    {
        return Pool::query()
            ->whereHas('members', function ($q) {
                $q->where('user_id', Auth::id())
                  ->whereIn('role', ['owner', 'manager'])
                  ->where('status', 'active');
            })
            ->where('status', 'active')
            ->with(['competition:id,code,name', 'season:id,competition_id,year'])
            ->orderBy('competition_id')
            ->orderBy('name');
    }

    private function selectedPool(): ?Pool
    {
        if (! $this->selectedPoolId) {
            return null;
        }
        return $this->myPools()->find($this->selectedPoolId);
    }

    public function render()
    {
        $pools = $this->myPools()->withCount([
            'members as total_members',
            'members as pending_count' => fn ($q) => $q->where('status', 'pending'),
            'members as active_count'  => fn ($q) => $q->where('status', 'active'),
        ])->get();

        $groupedPools = $pools->groupBy(function (Pool $pool) {
            $code = strtoupper((string) ($pool->competition?->code ?? 'SEM'));
            $name = (string) ($pool->competition?->name ?? 'Sem competição');
            $season = (string) ($pool->season?->year ?? '—');

            return "{$code}|{$name}|{$season}";
        });

        $selectedPool = $this->selectedPool();

        $members = collect();
        $statusCounts = collect();

        if ($selectedPool) {
            $query = $selectedPool->members()
                ->with('user:id,name,display_name,area,email')
                ->orderByRaw("case status when 'pending' then 0 when 'active' then 1 when 'inactive' then 2 when 'blocked' then 3 else 4 end")
                ->orderByRaw("case role when 'owner' then 0 when 'manager' then 1 else 2 end")
                ->orderBy('id');

            if ($this->filterStatus) {
                $query->where('status', $this->filterStatus);
            }

            if ($this->search !== '') {
                $query->whereHas('user', function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                      ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            }

            $members = $query->get();

            $statusCounts = $selectedPool->members()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');
        }

        return view('livewire.management.my-pools-manager', compact('pools', 'groupedPools', 'selectedPool', 'members', 'statusCounts'));
    }
}
