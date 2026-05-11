<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:80'],
            'area' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
        ], [
            'name.required' => 'Informe seu nome completo.',
            'name.max' => 'O nome não pode ter mais de 255 caracteres.',
            'display_name.required' => 'Informe seu apelido.',
            'display_name.max' => 'O apelido não pode ter mais de 80 caracteres.',
            'area.max' => 'A área/setor não pode ter mais de 100 caracteres.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'email.max' => 'O e-mail não pode ter mais de 255 caracteres.',
        ]);

        $temporaryPassword = Str::password(12, symbols: false);

        $user = User::create([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'area' => $request->area,
            'email' => $request->email,
            'password' => Hash::make($temporaryPassword),
            'status' => 'active',
            'must_change_password' => true,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
