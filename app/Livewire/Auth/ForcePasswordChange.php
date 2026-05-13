<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ForcePasswordChange extends Component
{
    public function save(string $password, string $passwordConfirmation): void
    {
        $user = auth()->user();

        if (! $user) {
            $this->dispatch('swal:alert', [
                'icon' => 'error',
                'title' => 'Sessão expirada',
                'text' => 'Sua sessão expirou. Entre novamente.',
            ]);
            return;
        }

        $validator = Validator::make(
            [
                'password'              => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'password'         => ['required', 'confirmed', Password::defaults()],
            ],
            [
                'password.required'                 => 'Informe a nova senha.',
                'password.confirmed'                => 'A confirmacao da nova senha nao confere.',
            ]
        );

        if ($validator->fails()) {
            $this->dispatch('swal:alert', [
                'icon'  => 'error',
                'title' => 'Erro ao trocar senha',
                'text'  => $validator->errors()->first() ?: 'Verifique os campos e tente novamente.',
            ]);
            foreach ($validator->errors()->toArray() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            return;
        }

        $user->update([
            'password'             => Hash::make($password),
            'must_change_password' => false,
            'password_changed_at'  => now(),
            'email_verified_at'    => $user->email_verified_at ?: now(),
        ]);

        $this->dispatch('swal:password-changed', [
            'title'    => 'Senha alterada com sucesso',
            'text'     => 'Você será redirecionado para o dashboard.',
            'redirect' => route('dashboard'),
        ]);
    }

    public function render()
    {
        return view('livewire.auth.force-password-change');
    }
}
