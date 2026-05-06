<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ForcePasswordChange extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function save(): void
    {
        $user = auth()->user();

        if (! $user) {
            $this->addError('current_password', 'Sua sessao expirou. Entre novamente.');
            return;
        }

        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'current_password.required' => 'Informe sua senha atual.',
            'current_password.current_password' => 'A senha atual esta incorreta.',
            'password.required' => 'Informe a nova senha.',
            'password.confirmed' => 'A confirmacao da nova senha nao confere.',
        ]);

        $user->update([
            'password' => Hash::make($this->password),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        session()->flash('status', 'Senha alterada com sucesso.');

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.force-password-change');
    }
}
