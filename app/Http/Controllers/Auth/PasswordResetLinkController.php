<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Notifications\PasswordResetRequestedNotification;
use App\Services\Email\EmailDispatchGuard;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, EmailDispatchGuard $emailGuard): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = mb_strtolower(trim((string) $request->input('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $limit = (int) config('email-limits.limits.password_reset_email_hour', 3);
            if (! $emailGuard->attempt('password_reset', $email, $limit, 3600)) {
                return back()->with('status', 'Se o e-mail existir, enviaremos as instruções em instantes.');
            }

            $token = Password::broker()->createToken($user);
            $user->notify(new PasswordResetRequestedNotification($token, [
                'ip' => (string) ($request->ip() ?? 'Não informado'),
                'user_agent' => (string) ($request->userAgent() ?? 'Não informado'),
                'requested_at' => now()->format('d/m/Y H:i:s').' (America/Sao_Paulo)',
            ]));
        }

        return back()->with('status', 'Se o e-mail existir, enviaremos as instruções em instantes.');
    }
}
