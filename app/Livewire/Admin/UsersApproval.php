<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class UsersApproval extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';

    public function mount(): void
    {
        $this->assertAdmin();
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }

    public function approve(int $userId): void
    {
        $this->assertAdmin();

        $user = User::whereKey($userId)->where('id', '!=', Auth::id())->first();
        if (! $user) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Usuário não encontrado.']);
            return;
        }

        $updated = User::whereKey($user->id)->update(['status' => 'active']);
        if ($updated !== 1) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Não foi possível aprovar o usuário.']);
            return;
        }

        $this->dispatch('swal:alert', ['icon' => 'success', 'title' => 'Sucesso', 'text' => "Usuário {$user->name} aprovado com sucesso."]);
    }

    public function reject(int $userId): void
    {
        $this->assertAdmin();

        $user = User::whereKey($userId)->where('id', '!=', Auth::id())->first();
        if (! $user) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Usuário não encontrado.']);
            return;
        }

        $updated = User::whereKey($user->id)->update(['status' => 'rejected']);
        if ($updated !== 1) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Não foi possível rejeitar o usuário.']);
            return;
        }

        $this->dispatch('swal:alert', ['icon' => 'success', 'title' => 'Sucesso', 'text' => "Usuário {$user->name} rejeitado."]);
    }

    public function suspend(int $userId): void
    {
        $this->assertAdmin();

        $user = User::whereKey($userId)->where('id', '!=', Auth::id())->first();
        if (! $user) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Usuário não encontrado.']);
            return;
        }

        $updated = User::whereKey($user->id)->update(['status' => 'suspended']);
        if ($updated !== 1) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Não foi possível suspender o usuário.']);
            return;
        }

        $this->dispatch('swal:alert', ['icon' => 'success', 'title' => 'Sucesso', 'text' => "Usuário {$user->name} suspenso."]);
    }

    public function toggleAdmin(int $userId): void
    {
        $this->assertAdmin();

        $user = User::whereKey($userId)->where('id', '!=', Auth::id())->first();
        if (! $user) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Usuário não encontrado.']);
            return;
        }

        $updated = User::whereKey($user->id)->update(['is_admin' => ! $user->is_admin]);
        if ($updated !== 1) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Não foi possível atualizar admin.']);
            return;
        }

        $this->dispatch('swal:alert', ['icon' => 'success', 'title' => 'Sucesso', 'text' => "Permissão de admin atualizada para {$user->name}."]);
    }

    private function assertAdmin(): void
    {
        abort_unless((bool) Auth::user()?->is_admin, 403);
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search, fn ($q) => $q->where(fn ($q) =>
                $q->where('name', 'like', '%'.$this->search.'%')
                  ->orWhere('email', 'like', '%'.$this->search.'%')
            ))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->orderByRaw("case status when 'pending' then 0 when 'active' then 1 else 2 end")
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $counts = User::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('livewire.admin.usersapproval', compact('users', 'counts'));
    }
}
