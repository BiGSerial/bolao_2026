<?php

namespace App\Console\Commands;

use App\Events\PoolRankingUpdated;
use App\Jobs\SendWebPushToUsersJob;
use App\Models\ApiSyncLog;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\PoolRanking;
use App\Models\Prediction;
use App\Services\Pools\PoolRankingService;
use App\Services\Predictions\PredictionScoringService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ConsolidateDailySportsResults extends Command
{
    protected $signature = 'sports:consolidate-daily-results
        {--date= : Data de referência no fuso local (YYYY-MM-DD)}
        {--timezone=America/Sao_Paulo : Fuso da consolidação}
        {--sync-limit=120 : Limite por competição para sync de detalhes finais}';

    protected $description = 'Consolida no fim do dia os resultados esportivos, pontuação e rankings dos bolões.';

    public function handle(PredictionScoringService $scoringService, PoolRankingService $rankingService): int
    {
        $startedAt = now();
        $timezone = (string) $this->option('timezone');
        $syncLimit = max(20, (int) $this->option('sync-limit'));
        $targetDate = $this->resolveTargetDate($timezone);

        $this->info("Consolidação diária iniciada para {$targetDate->toDateString()} ({$timezone}).");

        $competitions = collect((array) config('football-data.competitions', []))
            ->map(function (array $competition, string $key): ?array {
                $code = strtoupper((string) ($competition['code'] ?? $key));
                $enabled = (bool) ($competition['enabled'] ?? false);
                if (! $enabled && $code !== 'WC') {
                    return null;
                }

                $season = (int) ($competition['season'] ?? 0);
                if ($season <= 0 || $code === '') {
                    return null;
                }

                $stage = (string) ($competition['default_stage'] ?? '');
                return compact('code', 'season', 'stage');
            })
            ->filter()
            ->values();

        $syncRuns = 0;
        foreach ($competitions as $ctx) {
            Artisan::call('worldcup:sync-match-details', [
                '--code' => (string) $ctx['code'],
                '--season' => (int) $ctx['season'],
                '--stage' => (string) $ctx['stage'],
                '--limit' => $syncLimit,
                '--sync-type' => 'end_of_day_consolidation',
            ]);
            $syncRuns++;
        }

        $dayStartLocal = $targetDate->copy()->startOfDay();
        $dayEndLocal = $targetDate->copy()->endOfDay();
        $dayStartUtc = $dayStartLocal->copy()->utc();
        $dayEndUtc = $dayEndLocal->copy()->utc();

        $finishedMatchIdsForDay = FootballMatch::query()
            ->where('status', 'FINISHED')
            ->whereBetween('utc_date', [$dayStartUtc, $dayEndUtc])
            ->pluck('id')
            ->all();

        $pendingFinishedMatchIds = Prediction::query()
            ->join('football_matches', 'football_matches.id', '=', 'predictions.football_match_id')
            ->where('football_matches.status', 'FINISHED')
            ->whereNull('predictions.calculated_at')
            ->distinct()
            ->pluck('predictions.football_match_id')
            ->all();

        $finishedMatchIds = array_values(array_unique(array_merge(
            $finishedMatchIdsForDay,
            $pendingFinishedMatchIds
        )));

        if ($finishedMatchIds === []) {
            $this->info('Nenhuma partida finalizada no período para consolidar.');
            $this->logConsolidation($startedAt, $timezone, $targetDate->toDateString(), $syncRuns, 0, 0, 0, true);
            return self::SUCCESS;
        }

        $scoredPredictions = 0;
        Prediction::query()
            ->whereIn('football_match_id', $finishedMatchIds)
            ->chunkById(300, function ($predictions) use ($scoringService, &$scoredPredictions): void {
                foreach ($predictions as $prediction) {
                    $scoringService->calculate($prediction);
                    $scoredPredictions++;
                }
            });

        $poolIds = Prediction::query()
            ->whereIn('football_match_id', $finishedMatchIds)
            ->distinct()
            ->pluck('pool_id')
            ->all();

        $recalculatedPools = 0;
        $recalculatedPoolIds = [];
        Pool::query()
            ->whereIn('id', $poolIds)
            ->chunkById(100, function ($pools) use ($rankingService, &$recalculatedPools, &$recalculatedPoolIds): void {
                foreach ($pools as $pool) {
                    $rankingService->recalculate($pool);
                    PoolRankingUpdated::dispatch($pool);
                    $recalculatedPools++;
                    $recalculatedPoolIds[] = (int) $pool->id;
                }
            });

        $this->dispatchDailyRankingPushes(array_values(array_unique($recalculatedPoolIds)));

        $this->info(sprintf(
            'Consolidação concluída. Partidas: %d | Palpites recalculados: %d | Bolões reclassificados: %d | Syncs: %d',
            count($finishedMatchIds),
            $scoredPredictions,
            $recalculatedPools,
            $syncRuns
        ));

        $this->logConsolidation(
            $startedAt,
            $timezone,
            $targetDate->toDateString(),
            $syncRuns,
            count($finishedMatchIds),
            $scoredPredictions,
            $recalculatedPools,
            true
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<int>  $poolIds
     */
    private function dispatchDailyRankingPushes(array $poolIds): void
    {
        if (! config('push-notifications.daily_ranking_enabled', true) || $poolIds === []) {
            return;
        }

        PoolRanking::query()
            ->with(['pool:id,name', 'user:id,display_name,name'])
            ->whereIn('pool_id', $poolIds)
            ->orderBy('pool_id')
            ->orderBy('position')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $poolId = (int) $row->pool_id;
                    $userId = (int) $row->user_id;
                    $position = (int) $row->position;
                    $points = (int) $row->points_total;
                    $poolName = (string) ($row->pool?->name ?? 'Bolao');

                    SendWebPushToUsersJob::dispatch(
                        userIds: [$userId],
                        payload: [
                            'title' => "Resumo do dia - {$poolName}",
                            'body' => "Sua posicao atual: #{$position} com {$points} pts.",
                            'url' => "/pwa/pools/{$poolId}",
                            'tag' => "bolao-pool-{$poolId}",
                            'renotify' => false,
                        ],
                        dedupeKey: 'daily_ranking:'.now('America/Sao_Paulo')->toDateString().":{$poolId}:{$userId}",
                        dedupeSeconds: 21600,
                    )->onQueue((string) config('queue.connections.redis.queue', 'default'));
                }
            });
    }

    private function resolveTargetDate(string $timezone): Carbon
    {
        $rawDate = trim((string) $this->option('date'));
        if ($rawDate !== '') {
            return Carbon::createFromFormat('Y-m-d', $rawDate, $timezone);
        }

        $now = now($timezone);

        // Quando executado de madrugada (00h–05h59), consolida o dia anterior.
        // Isso garante que jogos iniciados às 23h BRT (que terminam após meia-noite) sejam incluídos.
        if ($now->hour < 6) {
            return $now->copy()->subDay();
        }

        return $now;
    }

    private function logConsolidation(
        Carbon $startedAt,
        string $timezone,
        string $date,
        int $syncRuns,
        int $matchesCount,
        int $predictionsCount,
        int $poolsCount,
        bool $success
    ): void {
        ApiSyncLog::query()->create([
            'provider' => 'system',
            'endpoint' => '/sports/daily-consolidation',
            'http_status' => 200,
            'success' => $success,
            'records_total' => $matchesCount,
            'records_changed' => $predictionsCount,
            'message' => 'Consolidação diária de resultados esportivos executada.',
            'meta' => [
                'date' => $date,
                'timezone' => $timezone,
                'sync_runs' => $syncRuns,
                'matches_finished' => $matchesCount,
                'predictions_recalculated' => $predictionsCount,
                'pools_recalculated' => $poolsCount,
                'duration_seconds' => now()->diffInSeconds($startedAt),
            ],
            'synced_at' => now(),
        ]);
    }
}
