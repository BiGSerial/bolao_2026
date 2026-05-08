<?php

namespace App\Livewire\Admin;

use App\Models\Pool;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class PoolsControl extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';
    public ?int $selectedPoolId = null;

    public function mount(): void
    {
        $this->assertAdmin();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function selectPool(int $poolId): void
    {
        $this->selectedPoolId = $poolId;
    }

    public function deactivate(int $poolId): void
    {
        $this->assertAdmin();

        $pool = Pool::query()->whereKey($poolId)->first();
        if (! $pool) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Grupo não encontrado.']);
            return;
        }

        $pool->update(['status' => 'blocked']);
        $this->dispatch('swal:alert', ['icon' => 'success', 'title' => 'Grupo desativado', 'text' => "O grupo {$pool->name} foi desativado."]);
    }

    public function reactivate(int $poolId): void
    {
        $this->assertAdmin();

        $pool = Pool::query()->whereKey($poolId)->first();
        if (! $pool) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Grupo não encontrado.']);
            return;
        }

        $pool->update(['status' => 'active']);
        $this->dispatch('swal:alert', ['icon' => 'success', 'title' => 'Grupo reativado', 'text' => "O grupo {$pool->name} foi reativado."]);
    }

    private function assertAdmin(): void
    {
        abort_unless((bool) Auth::user()?->is_admin, 403);
    }

    public function render()
    {
        $pools = Pool::query()
            ->when($this->search, fn ($q) => $q->where(fn ($q) =>
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('slug', 'like', '%'.$this->search.'%')
                    ->orWhere('invite_code', 'like', '%'.$this->search.'%')
            ))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->with(['owner:id,name,display_name'])
            ->withCount('members')
            ->orderByRaw("case status when 'active' then 0 when 'blocked' then 1 else 2 end")
            ->orderByDesc('id')
            ->paginate(20);

        $selectedPoolId = $this->selectedPoolId;
        if (! $selectedPoolId || ! $pools->getCollection()->contains(fn (Pool $pool) => $pool->id === $selectedPoolId)) {
            $selectedPoolId = $pools->first()?->id;
            $this->selectedPoolId = $selectedPoolId;
        }

        $selectedPool = null;
        if ($selectedPoolId) {
            $selectedPool = Pool::query()
                ->with([
                    'owner:id,name,display_name,email',
                    'members' => fn ($q) => $q
                        ->with('user:id,name,display_name,email,area')
                        ->orderByRaw("case role when 'owner' then 0 when 'manager' then 1 else 2 end")
                        ->orderBy('id'),
                ])
                ->withCount('members')
                ->find($selectedPoolId);
        }

        $counts = Pool::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('livewire.admin.pools-control', compact('pools', 'counts', 'selectedPool'));
    }
}
