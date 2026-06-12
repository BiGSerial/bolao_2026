<?php

use App\Jobs\RunCompetitionMatchDetailsSyncJob;
use App\Jobs\RunCompetitionSyncJob;
use App\Models\FootballMatch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('mail:stats:sync')
    ->timezone('America/Sao_Paulo')
    ->hourly()
    ->name('mail:stats:hourly-sync')
    ->withoutOverlapping(55);
// Executa às 02:30h BRT para garantir que jogos de 23h já tenham terminado antes da consolidação.
// O comando detecta automaticamente que está rodando de madrugada e consolida o dia anterior.
Schedule::command('sports:consolidate-daily-results --timezone=America/Sao_Paulo')
    ->timezone('America/Sao_Paulo')
    ->dailyAt('02:30')
    ->name('sports:daily-consolidation')
    ->withoutOverlapping(120);

Artisan::command('inspire', function () {
    $this->comment('Bolao 2026 pronto para sincronizacao.');
})->purpose('Display an informational message');

Schedule::call(function (): void {
    $competitions = collect((array) config('football-data.competitions', []))
        ->map(function (array $competition, string $key): ?array {
            $code    = strtoupper((string) ($competition['code'] ?? $key));
            $enabled = (bool) ($competition['enabled'] ?? false);
            if (! $enabled && $code !== 'WC') {
                return null;
            }
            $season = (int) ($competition['season'] ?? 2026);
            $stage  = (string) ($competition['default_stage'] ?? 'GROUP_STAGE');
            if ($season <= 0 || $stage === '') {
                return null;
            }
            return compact('code', 'season', 'stage');
        })
        ->filter()
        ->values();

    foreach ($competitions as $ctx) {
        $code   = (string) $ctx['code'];
        $season = (int)    $ctx['season'];
        $stage  = (string) $ctx['stage'];
        $now    = now(); // America/Sao_Paulo — corresponde a local_date no banco

        $base = FootballMatch::query()
            ->whereHas('competition', fn ($q) => $q->where('code', $code))
            ->whereHas('season',      fn ($q) => $q->where('year', $season));

        // ── Detecção por status ─────────────────────────────────────────────
        $liveCount = (clone $base)
            ->whereIn('status', ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'])
            ->count();
        $hasLive = $liveCount > 0;

        // ── Detecção temporal (local_date = horário SP no banco) ────────────

        // Partidas que começam nos próximos 15 min (pré-jogo imediato)
        $hasPreMatchWindow = (clone $base)
            ->whereNotIn('status', ['FINISHED', 'CANCELLED', 'POSTPONED', 'SUSPENDED'])
            ->whereBetween('local_date', [$now, $now->copy()->addMinutes(15)])
            ->exists();

        // Partidas que começam entre 15 min e 60 min (aviso antecipado)
        $hasSoon = ! $hasLive && ! $hasPreMatchWindow && (clone $base)
            ->whereNotIn('status', ['FINISHED', 'CANCELLED', 'POSTPONED', 'SUSPENDED'])
            ->whereBetween('local_date', [$now->copy()->addMinutes(15), $now->copy()->addHour()])
            ->exists();

        // Partidas que já deveriam estar ao vivo pelo horário (kickoff nas últimas 3h)
        $hasLikelyInProgressByKickoffWindow = ! $hasLive && (clone $base)
            ->whereNotIn('status', ['FINISHED', 'POSTPONED', 'CANCELLED', 'SUSPENDED'])
            ->whereBetween('local_date', [$now->copy()->subHours(3), $now])
            ->exists();

        // Partidas finalizadas há menos de 30 min (pós-jogo confirmado)
        $hasPostFinishWindow = (clone $base)
            ->where('finished_at', '>=', $now->copy()->subMinutes(30))
            ->exists();

        // Fallback: partidas que provavelmente terminaram mas finished_at ainda é null
        // (kickoff entre 90 e 210 min atrás, sem status final)
        $hasPostMatchFallbackWindow = ! $hasPostFinishWindow && (clone $base)
            ->whereNotIn('status', ['FINISHED', 'CANCELLED', 'POSTPONED', 'SUSPENDED'])
            ->whereBetween('local_date', [$now->copy()->subMinutes(210), $now->copy()->subMinutes(90)])
            ->exists();

        $detailsLimit = max(15, min(60, $liveCount + 6));

        if ($hasLive || $hasLikelyInProgressByKickoffWindow) {
            /*
             * ── Ao vivo (status ou janela temporal) ─────────────────────
             * Apenas API paga. A API gratuita fica suspensa durante o jogo.
             *
             * live_score_quick (cada 1 min): placar/status/live_clock + MatchUpdated
             * live_full_events (cada 2 min): eventos, lineups, stats + MatchDetailUpdated
             * Proporção 2:1 (score:full) — sem Football-Data neste bloco.
             */
            $scoreDispatched  = shouldDispatchForWindow("scheduler:af-score:$code:$season:$stage", 1, 1);
            $eventsDispatched = shouldDispatchForWindow("scheduler:af-events:$code:$season:$stage", 2, 2);

            if ($scoreDispatched) {
                RunCompetitionMatchDetailsSyncJob::dispatch($code, $season, $stage, $detailsLimit, 'live_score_quick');
            }

            if ($eventsDispatched) {
                RunCompetitionMatchDetailsSyncJob::dispatch($code, $season, $stage, $detailsLimit, 'live_full_events');
            }

            Log::channel('smart_sync')->info('[smart-sync] live', [
                'code'                               => $code,
                'season'                             => $season,
                'stage'                              => $stage,
                'now'                                => $now->toDateTimeString(),
                'hasLive'                            => $hasLive,
                'hasLikelyInProgressByKickoffWindow' => $hasLikelyInProgressByKickoffWindow,
                'liveCount'                          => $liveCount,
                'detailsLimit'                       => $detailsLimit,
                'scoreDispatched'                    => $scoreDispatched,
                'eventsDispatched'                   => $eventsDispatched,
            ]);
        } else {
            /*
             * ── Fora de ao vivo ──────────────────────────────────────────
             * FD (gratuita) suspensa em pré-jogo imediato (≤15 min).
             * Retoma no pós-jogo e idle.
             */
            if (! $hasPreMatchWindow) {
                [$fdMin, $fdMax, $fdMode] = match(true) {
                    $hasSoon                                                => [2, 4,  'pre'],
                    $hasPostFinishWindow || $hasPostMatchFallbackWindow     => [2, 3,  'post'],
                    default                                                 => [7, 10, 'idle'],
                };

                if (shouldDispatchForWindow("scheduler:fd:$code:$season:$stage:$fdMode", $fdMin, $fdMax)) {
                    RunCompetitionSyncJob::dispatch($code, $season, $stage);
                }
            }

            [$afMin, $afMax, $afLimit, $syncType] = match(true) {
                $hasPreMatchWindow                                          => [1, 2,   8,  'pre_live_priority'],
                $hasSoon                                                    => [3, 5,   5,  'pre_live_priority'],
                $hasPostFinishWindow || $hasPostMatchFallbackWindow         => [2, 3,   5,  'post_match_cleanup'],
                default                                                     => [60, 60, 20, 'idle_full_stats_daily'],
            };

            $afCacheKey  = "scheduler:af:$code:$season:$stage:$syncType";
            $stateKey    = "scheduler:decision:$code:$season:$stage";
            $heartbeatKey = "scheduler:heartbeat:$code:$season:$stage";

            $lastSyncType  = Cache::get($stateKey);
            $stateChanged  = $lastSyncType !== $syncType;
            $isActiveMode  = $syncType !== 'idle_full_stats_daily';

            // Registra quando: estado mudou, modo ativo, ou heartbeat idle a cada 15 min
            $shouldLog = $stateChanged
                || $isActiveMode
                || ! Cache::has($heartbeatKey);

            if ($shouldLog) {
                if ($stateChanged) {
                    Cache::put($stateKey, $syncType, now()->addDay());
                }
                if (! $isActiveMode) {
                    Cache::put($heartbeatKey, true, now()->addMinutes(15));
                }

                Log::channel('smart_sync')->info('[smart-sync] decision', [
                    'code'                               => $code,
                    'season'                             => $season,
                    'stage'                              => $stage,
                    'now'                                => $now->toDateTimeString(),
                    'hasLive'                            => $hasLive,
                    'hasPreMatchWindow'                  => $hasPreMatchWindow,
                    'hasSoon'                            => $hasSoon,
                    'hasLikelyInProgressByKickoffWindow' => $hasLikelyInProgressByKickoffWindow,
                    'hasPostFinishWindow'                => $hasPostFinishWindow,
                    'hasPostMatchFallbackWindow'         => $hasPostMatchFallbackWindow,
                    'syncType'                           => $syncType,
                    'afMin'                              => $afMin,
                    'afMax'                              => $afMax,
                    'afLimit'                            => $afLimit,
                    'afCacheKey'                         => $afCacheKey,
                    'nextDue'                            => Cache::get($afCacheKey)?->toDateTimeString(),
                    'stateChanged'                       => $stateChanged,
                ]);
            }

            if (shouldDispatchForWindow($afCacheKey, $afMin, $afMax)) {
                RunCompetitionMatchDetailsSyncJob::dispatch($code, $season, $stage, $afLimit, $syncType);
            }
        }
    }
})
    ->name('queue:smart-sync-orchestrator')
    ->everyMinute()
    ->withoutOverlapping();

/**
 * Decide despacho com janela randômica e guarda "next due" em cache.
 */
if (! function_exists('shouldDispatchForWindow')) {
    function shouldDispatchForWindow(string $cacheKey, int $minMinutes, int $maxMinutes): bool
    {
        $nextDue = Cache::get($cacheKey);

        if ($nextDue instanceof \Carbon\CarbonInterface && $nextDue->isFuture()) {
            return false;
        }

        $delay = random_int(max(1, $minMinutes), max($minMinutes, $maxMinutes));
        Cache::put($cacheKey, now()->addMinutes($delay), now()->addDay());

        return true;
    }
}
