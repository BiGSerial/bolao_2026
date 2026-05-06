<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserModerationController extends Controller
{
    public function approve(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === Auth::id(), 403);

        $user->update(['status' => 'active']);

        return back()->with('status', "Usuário {$user->name} aprovado com sucesso.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === Auth::id(), 403);

        $user->update(['status' => 'rejected']);

        return back()->with('status', "Usuário {$user->name} rejeitado.");
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === Auth::id(), 403);

        $user->update(['status' => 'suspended']);

        return back()->with('status', "Usuário {$user->name} suspenso.");
    }
}

