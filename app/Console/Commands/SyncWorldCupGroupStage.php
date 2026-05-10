<?php

namespace App\Console\Commands;

use App\Jobs\CalculatePredictionsForMatchJob;
use App\Jobs\RecalculatePoolRankingsJob;
use App\Models\ApiSyncLog;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Services\FootballData\FootballDataClient;
use App\Services\FootballData\SyncWorldCupMatchesService;
use App\Services\FootballData\SyncWorldCupStandingsService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Throwable;

class SyncWorldCupGroupStage extends Command
{
    protected $signature = 'worldcup:sync-group-stage
        {--code= : Codigo da competicao (ex: WC, BSA)}
        {--season= : Temporada (ex: 2026)}
        {--stage= : Fase para filtro (ex: GROUP_STAGE)}
        {--force : Ignora janela inteligente e sincroniza agora}';

    protected $description = 'Sincroniza jogos e classificacao por competicao/temporada.';

    public function handle(
        FootballDataClient $client,
        SyncWorldCupMatchesService $syncService,
        SyncWorldCupStandingsService $standingsSyncService
    ): int {
        $ctx = $client->competitionContext(
            $this->option('code') ? (string) $this->option('code') : null,
            $this->option('season') ? (int) $this->option('season') : null,
            $this->option('stage') ? (string) $this->option('stage') : null
        );

        if (! $this->option('force') && ! $this->shouldSyncNow($ctx['code'], $ctx['season'], $ctx['stage'])) {
            ApiSyncLog::create([
                'provider' => 'football_data',
                'endpoint' => '/competitions/'.$ctx['code'].'/matches',
                'success' => true,
                'message' => 'Sync ignorado: fora da janela de sincronizacao.',
                'meta' => [
                    'apis_synced' => ['football_data'],
                    'status' => 'skipped',
                    'competition_code' => $ctx['code'],
                    'season' => $ctx['season'],
                    'stage' => $ctx['stage'],
                ],
                'synced_at' => now(),
            ]);

            $this->info('Fora da janela de sincronizacao.');

            return self::SUCCESS;
        }

        try {
            $payload = $client->competitionMatches($ctx['code'], $ctx['season'], $ctx['stage']);
            $changed = $syncService->sync($payload, $ctx['season']);

            $standingsPayload = $client->competitionStandings($ctx['code'], $ctx['season']);
            $standingsCount = $standingsSyncService->sync($standingsPayload, $ctx['season'], $ctx['stage']);

            foreach ($changed as $match) {
                if ($match->status === 'FINISHED') {
                    CalculatePredictionsForMatchJob::dispatch($match->id);
                }
            }

            if ($changed->isNotEmpty()) {
                Pool::query()->pluck('id')->each(fn (int $poolId) => RecalculatePoolRankingsJob::dispatch($poolId));
            }

            ApiSyncLog::create([
                'provider' => 'football_data',
                'endpoint' => '/competitions/'.$ctx['code'].'/matches',
                'http_status' => 200,
                'success' => true,
                'records_total' => count($payload['matches'] ?? []),
                'records_changed' => $changed->count(),
                'message' => 'Sync executado com sucesso.',
                'meta' => [
                    'apis_synced' => ['football_data'],
                    'status' => 'success',
                    'competition_code' => $ctx['code'],
                    'season' => $ctx['season'],
                    'stage' => $ctx['stage'],
                    'result_set' => data_get($payload, 'resultSet'),
                    'standings_synced' => $standingsCount,
                ],
                'synced_at' => now(),
            ]);

            $this->info('Sincronizacao concluida. Jogos alterados: '.$changed->count().' | Grupos sincronizados: '.$standingsCount);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $httpStatus = $e instanceof RequestException ? $e->response?->status() : null;

            ApiSyncLog::create([
                'provider' => 'football_data',
                'endpoint' => '/competitions/'.$ctx['code'].'/matches',
                'http_status' => $httpStatus,
                'success' => false,
                'message' => $e->getMessage(),
                'meta' => [
                    'apis_synced' => ['football_data'],
                    'status' => $httpStatus === 429 ? 'rate_limited' : 'failed',
                    'competition_code' => $ctx['code'],
                    'season' => $ctx['season'],
                    'stage' => $ctx['stage'],
                    'exception' => get_class($e),
                ],
                'synced_at' => now(),
            ]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function shouldSyncNow(string $code, int $season, ?string $stage): bool
    {
        $baseQuery = FootballMatch::query()
            ->whereHas('competition', fn ($q) => $q->where('code', $code))
            ->whereHas('season', fn ($q) => $q->where('year', $season));

        if ($stage !== null && $stage !== '') {
            $baseQuery->where('stage', $stage);
        }

        if (! $baseQuery->exists()) {
            return true;
        }

        return (clone $baseQuery)
            ->whereBetween('utc_date', [
                now()->utc()->subHours(1),
                now()->utc()->addHours(4),
            ])
            ->whereIn('status', ['TIMED', 'SCHEDULED', 'IN_PLAY', 'PAUSED'])
            ->exists();
    }
}
