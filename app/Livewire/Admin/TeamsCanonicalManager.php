<?php

namespace App\Livewire\Admin;

use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class TeamsCanonicalManager extends Component
{
    use WithPagination;

    public string $search = '';

    /** @var array<int, string> */
    public array $canonicalNames = [];

    public function mount(): void
    {
        $this->assertAdmin();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function saveTeam(int $teamId): void
    {
        $this->assertAdmin();

        $team = Team::query()->find($teamId);
        if (! $team) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Time não encontrado.']);
            return;
        }

        $value = trim((string) ($this->canonicalNames[$teamId] ?? ''));
        $team->update([
            'canonical_name_br' => $value !== '' ? $value : null,
        ]);

        $this->canonicalNames[$teamId] = (string) ($team->canonical_name_br ?? '');

        $this->dispatch('swal:alert', [
            'icon' => 'success',
            'title' => 'Salvo',
            'text' => "Nome canônico atualizado para {$team->name}.",
        ]);
    }

    private function assertAdmin(): void
    {
        abort_unless((bool) Auth::user()?->is_admin, 403);
    }

    public function render()
    {
        $this->assertAdmin();

        $teams = Team::query()
            ->when($this->search !== '', function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where(function ($w) use ($term): void {
                    $w->where('name', 'like', $term)
                        ->orWhere('short_name', 'like', $term)
                        ->orWhere('tla', 'like', $term)
                        ->orWhere('canonical_name_br', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(25);

        foreach ($teams as $team) {
            $this->canonicalNames[$team->id] = (string) ($this->canonicalNames[$team->id] ?? ($team->canonical_name_br ?? ''));
        }

        return view('livewire.admin.teams-canonical-manager', compact('teams'));
    }
}
