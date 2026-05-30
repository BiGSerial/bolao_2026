<?php

use App\Models\PoolMember;
use Illuminate\Support\Facades\Broadcast;

// Aceita autenticação via sessão web (Livewire) e Bearer token (PWA/API).
Broadcast::routes(['middleware' => ['auth:sanctum']]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('pool.{poolId}', function ($user, $poolId) {
    return PoolMember::query()
        ->where('pool_id', $poolId)
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->exists();
});
