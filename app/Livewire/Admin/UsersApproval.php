<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Notifications\WelcomeWithTemporaryPasswordNotification;
use App\Services\Email\EmailDispatchGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;
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

    public function ban(int $userId): void
    {
        $this->assertAdmin();

        $user = User::whereKey($userId)->where('id', '!=', Auth::id())->first();
        if (! $user) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Usuário não encontrado.']);
            return;
        }

        $updated = User::whereKey($user->id)->update([
            'status' => 'banned',
            'is_admin' => false,
            'remember_token' => null,
        ]);

        if ($updated !== 1) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Não foi possível banir o usuário.']);
            return;
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->dispatch('swal:alert', ['icon' => 'success', 'title' => 'Usuário banido', 'text' => "A conta de {$user->name} foi banida da plataforma."]);
    }

    public function destroyUserData(int $userId): void
    {
        $this->assertAdmin();

        $user = User::whereKey($userId)->where('id', '!=', Auth::id())->first();
        if (! $user) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Usuário não encontrado.']);
            return;
        }

        $name = $user->name;
        $user->delete();

        $this->dispatch('swal:alert', ['icon' => 'success', 'title' => 'Dados removidos', 'text' => "Todos os dados de {$name} foram removidos."]);
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

    public function resendTemporaryPassword(int $userId): void
    {
        $this->assertAdmin();

        $user = User::whereKey($userId)->where('id', '!=', Auth::id())->first();
        if (! $user) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Usuário não encontrado.']);
            return;
        }

        try {
            $emailGuard = app(EmailDispatchGuard::class);
            $tempLimit = (int) config('email-limits.limits.temporary_password_user_day', 2);
            if (! $emailGuard->attempt('temporary_password', (string) $user->id, $tempLimit, 86400)) {
                $this->dispatch('swal:alert', [
                    'icon' => 'warning',
                    'title' => 'Limite de envio',
                    'text' => 'Limite de e-mails para este usuário atingido nas últimas 24h ou cota mensal esgotada.',
                ]);
                return;
            }

            $temporaryPassword = Str::password(12, symbols: false);

            $user->forceFill([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'password_changed_at' => null,
                'temporary_password_expires_at' => now()->addHours(13),
            ])->save();

            $user->notify(new WelcomeWithTemporaryPasswordNotification($temporaryPassword));

            $this->dispatch('swal:alert', [
                'icon' => 'success',
                'title' => 'Senha reenviada',
                'text' => "Nova senha temporária enviada para {$user->email}.",
            ]);
        } catch (Throwable $e) {
            report($e);

            $this->dispatch('swal:alert', [
                'icon' => 'error',
                'title' => 'Falha no envio',
                'text' => 'Não foi possível reenviar a senha temporária no momento.',
            ]);
        }
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
            ->orderByRaw("case status when 'pending' then 0 when 'active' then 1 when 'suspended' then 2 when 'rejected' then 3 when 'banned' then 4 else 5 end")
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $counts = User::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('livewire.admin.usersapproval', compact('users', 'counts'));
    }
}
