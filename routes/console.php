<?php

use App\Jobs\RunCompetitionMatchDetailsSyncJob;
use App\Jobs\RunCompetitionSyncJob;
use App\Models\FootballMatch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('sports:consolidate-daily-results --timezone=America/Sao_Paulo')
    ->timezone('America/Sao_Paulo')
    ->dailyAt('23:59')
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

        $base = FootballMatch::query()
            ->whereHas('competition', fn ($q) => $q->where('code', $code))
            ->whereHas('season',      fn ($q) => $q->where('year', $season));

        // Detecta estado atual da competição
        $liveCount = (clone $base)
            ->whereIn('status', ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'])
            ->count();
        $hasLive = $liveCount > 0;

        $hasPreMatch = ! $hasLive && (clone $base)
            ->where('status', 'PRE_MATCH')
            ->exists();

        $hasSoon = ! $hasLive && ! $hasPreMatch && (clone $base)
            ->whereIn('status', ['TIMED', 'SCHEDULED'])
            ->where('utc_date', '<=', now()->addHour())
            ->exists();
        $hasLikelyInProgressByKickoffWindow = ! $hasLive && (clone $base)
            ->whereNotIn('status', ['FINISHED', 'POSTPONED', 'CANCELLED', 'SUSPENDED'])
            ->whereBetween('utc_date', [now()->subHours(4), now()->addHours(2)])
            ->exists();

        /*
         * ── Football-Data (gratuita · 10 RPM) ───────────────────────────
         * Fora de ao vivo: mantém calendário, status e placares gerais.
         * Durante ao vivo: NÃO despacha sync base (somente fallback na camada de detalhes).
         */
        if (! $hasLive) {
            [$fdMin, $fdMax] = match(true) {
                $hasPreMatch => [2, 3],
                $hasSoon     => [2, 4],
                default      => [7, 10],
            };

            if (shouldDispatchForWindow("scheduler:fd:$code:$season:$stage", $fdMin, $fdMax)) {
                RunCompetitionSyncJob::dispatch($code, $season, $stage);
            }
        }

        /*
         * ── API-Football (paga · 125 RPM / 7500 por hora) ───────────────
         * Responsável por escalações, estatísticas e eventos detalhados.
         * Prioridade máxima durante jogos ao vivo — batches grandes e janelas curtas.
         * Sem jogos: janela longa para economizar cota.
         *
         * Limite por batch:
         *   15 = até 15 req Football-Data (rate-limiter cuida) + ~1 req API-Football (bulk 20)
         *    8 = pré-jogo (lineups + eventos de aquecimento)
         *    5 = jogo próximo (atualização de prováveis escalações)
         *    3 = ocioso (backfill de detalhes antigos)
         */
        [$afMin, $afMax, $detailsLimit, $syncType] = match(true) {
            // Garante cobertura de TODOS os jogos ao vivo no batch de detalhes
            // (lineups/eventos) com folga para pré-jogos próximos.
            $hasLive     => [1, 1,  max(15, min(60, $liveCount + 6)), (now()->minute % 3 === 0 ? 'live_stats_refresh' : 'live_minute_tick')],
            $hasPreMatch => [2, 3,   8, 'pre_live_priority'],
            $hasSoon     => [3, 5,   5, 'pre_live_priority'],
            default      => [30, 45, 2, 'scheduled_auto'],
        };

        $shouldUsePaidApi = $hasLive || $hasPreMatch || $hasSoon || $hasLikelyInProgressByKickoffWindow;
        if ($shouldUsePaidApi && shouldDispatchForWindow("scheduler:af:$code:$season:$stage", $afMin, $afMax)) {
            if ($hasLikelyInProgressByKickoffWindow && ! $hasLive && ! $hasPreMatch && ! $hasSoon) {
                $syncType = 'kickoff_window_priority';
            }
            RunCompetitionMatchDetailsSyncJob::dispatch($code, $season, $stage, $detailsLimit, $syncType);
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
