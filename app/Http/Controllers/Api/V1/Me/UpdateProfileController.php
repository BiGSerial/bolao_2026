<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => ['sometimes', 'required', 'string', 'min:2', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:191', "unique:users,email,{$user->id}"],
        ]);

        $emailChanged = isset($validated['email']) && $validated['email'] !== $user->email;

        if ($emailChanged) {
            $validated['email_verified_at'] = null;
        }

        $user->fill($validated)->save();

        if ($emailChanged) {
            event(new Registered($user));
        }

        return ApiResponse::success($request, [
            'name'              => $user->name,
            'email'             => $user->email,
            'email_verified'    => $user->email_verified_at !== null,
            'email_changed'     => $emailChanged,
        ]);
    }
}
