<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class PublicRegister extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function updatedPhone(string $value): void
    {
        $this->phone = $this->formatPhoneMask($value);
    }

    public function save(): void
    {
        $normalizedPhone = $this->normalizePhone($this->phone);

        try {
            $this->validate([
                'name'     => ['required', 'string', 'max:120'],
                'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'phone'    => [
                    'required',
                    'string',
                    'regex:/^\(\d{2}\)\s\d{4,5}-\d{4}$/',
                    Rule::unique('users', 'phone')->where(fn ($q) => $q->where('phone', $normalizedPhone)),
                ],
                'password' => ['required', 'confirmed', Password::defaults()],
            ], [
                'name.required'             => 'Informe seu nome completo.',
                'name.max'                  => 'O nome não pode ter mais de 120 caracteres.',
                'email.required'            => 'Informe seu e-mail.',
                'email.email'               => 'Informe um e-mail válido.',
                'email.unique'              => 'Este e-mail já está em uso.',
                'phone.required'            => 'Informe seu telefone.',
                'phone.regex'               => 'Telefone inválido. Use o formato (DD) 99999-9999.',
                'phone.unique'              => 'Este telefone já está cadastrado.',
                'password.required'         => 'Defina uma senha.',
                'password.confirmed'        => 'As senhas não conferem.',
                'password.min'              => 'A senha deve ter no mínimo 8 caracteres.',
            ]);
        } catch (ValidationException $exception) {
            $this->dispatch('swal:alert', [
                'icon' => 'error',
                'title' => 'Não foi possível concluir o cadastro',
                'messages' => $exception->validator->errors()->all(),
            ]);
            throw $exception;
        }

        User::create([
            'name'     => $this->name,
            'email'    => $this->email,
            'phone'    => $normalizedPhone,
            'password' => Hash::make($this->password),
        ]);

        session()->flash('register_success', true);
        $this->redirectRoute('login', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.publicregister');
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) < 10 || strlen($digits) > 11) {
            return $digits;
        }

        return '55' . $digits;
    }

    private function formatPhoneMask(string $value): string
    {
        $digits = substr(preg_replace('/\D+/', '', $value) ?? '', 0, 11);
        $length = strlen($digits);

        if ($length <= 2) {
            return $digits === '' ? '' : '(' . $digits;
        }

        $ddd  = substr($digits, 0, 2);
        $rest = substr($digits, 2);

        if (strlen($rest) <= 4) {
            return sprintf('(%s) %s', $ddd, $rest);
        }

        if (strlen($rest) <= 8) {
            return sprintf('(%s) %s-%s', $ddd, substr($rest, 0, 4), substr($rest, 4));
        }

        return sprintf('(%s) %s-%s', $ddd, substr($rest, 0, 5), substr($rest, 5, 4));
    }
}
