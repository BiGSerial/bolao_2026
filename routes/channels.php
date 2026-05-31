<?php

use App\Models\PoolMember;
use Illuminate\Support\Facades\Broadcast;

// Web/Livewire: autenticação de canais privados via sessão/cookie.
// PWA/API (Bearer token) continua em routes/api.php -> /api/broadcasting/auth.
Broadcast::routes(['middleware' => ['web', 'auth']]);

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

Broadcast::channel('pool-chat.{poolId}', function ($user, $poolId) {
    return PoolMember::query()
        ->where('pool_id', $poolId)
        ->where('user_id', $user->id)
        ->whereIn('status', ['active', 'pending'])
        ->exists();
});
