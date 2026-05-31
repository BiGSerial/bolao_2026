<?php

use App\Models\FootballMatch;
use App\Models\PoolMember;
use Illuminate\Support\Facades\Broadcast;

// Web/Livewire: autenticação de canais privados via sessão/cookie.
// PWA/API (Bearer token) continua em routes/api.php -> /api/broadcasting/auth.
Broadcast::routes(['middleware' => ['web', 'auth']]);

// Canal padrão legado do Laravel para notificações de usuário.
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Novo padrão explícito para eventos individuais.
Broadcast::channel('users.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Compatibilidade com canal atual do projeto (singular).
Broadcast::channel('pool.{poolId}', function ($user, $poolId) {
    return PoolMember::query()
        ->where('pool_id', $poolId)
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->exists();
});

// Novo alias plural para padronização dos novos eventos.
Broadcast::channel('pools.{poolId}', function ($user, $poolId) {
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

Broadcast::channel('matches.{matchId}', function ($user, $matchId) {
    // Regra inicial: apenas partidas existentes; filtros adicionais
    // (por competição/bolão/preferência) serão aplicados na audiência do envio.
    return FootballMatch::query()->whereKey((int) $matchId)->exists();
});
