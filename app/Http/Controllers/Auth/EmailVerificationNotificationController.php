<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Email\EmailDispatchGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request, EmailDispatchGuard $emailGuard): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $limit = (int) config('email-limits.limits.email_verification_user_hour', 3);
        if (! $emailGuard->attempt('email_verification', (string) $request->user()->id, $limit, 3600)) {
            return back()->with('status', 'Aguarde alguns minutos antes de solicitar um novo e-mail de verificação.');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
