<?php

namespace App\Console\Commands;

use App\Jobs\CalculatePredictionsForMatchJob;
use App\Models\ApiSyncLog;
use App\Services\FootballData\FootballDataClient;
use App\Services\FootballData\SyncWorldCupMatchDetailsService;
use App\Services\FootballData\SyncWorldCupStandingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncWorldCupMatchDetails extends Command
{
    protected $signature = 'worldcup:sync-match-details
        {--limit=8 : Quantidade máxima de jogos por execução}
        {--code= : Codigo da competicao (ex: WC, BSA)}
        {--season= : Temporada (ex: 2026)}
        {--stage= : Fase para filtro (ex: GROUP_STAGE)}
        {--sync-type=scheduled_auto : Tipo de sincronizacao (ex: scheduled_auto, live_priority, complementary_off_live, manual_admin)}';

    protected $description = 'Sincroniza detalhes de partidas por competicao/temporada em lote, com cache em banco.';

    public function handle(
        SyncWorldCupMatchDetailsService $service,
        FootballDataClient $client,
        SyncWorldCupStandingsService $standingsSyncService
    ): int {
        $ctx = $client->competitionContext(
            $this->option('code') ? (string) $this->option('code') : null,
            $this->option('season') ? (int) $this->option('season') : null,
            $this->option('stage') ? (string) $this->option('stage') : null
        );

        $limit = max(1, (int) $this->option('limit'));
        $requestedSyncType = strtolower(trim((string) $this->option('sync-type')));
        if ($requestedSyncType === '') {
            $requestedSyncType = 'scheduled_auto';
        }

        $result = $service->syncBatch($limit, $ctx['code'], $ctx['season'], $ctx['stage'], $requestedSyncType);
        collect((array) ($result['finished_changed_match_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->each(fn (int $matchId) => CalculatePredictionsForMatchJob::dispatch($matchId));
        $standingsInfo = $this->syncStandingsIfDue($client, $standingsSyncService, $ctx['code'], $ctx['season'], $ctx['stage']);
        $apiFootballRequests = (int) ($result['api_football_requests'] ?? 0);
        $apiFootballFailures = (int) ($result['api_football_failures'] ?? 0);
        $apiFootballSyncType = (string) ($result['api_football_sync_type'] ?? 'not_used');
        $apisSynced = $apiFootballRequests > 0 ? ['football_data', 'api_football'] : ['football_data'];

        ApiSyncLog::create([
            'provider' => 'football_data',
            'endpoint' => '/competitions/'.$ctx['code'].'/matches/details',
            'http_status' => 200,
            'success' => ((int) ($result['errors'] ?? 0)) === 0,
            'records_total' => (int) ($result['selected'] ?? 0),
            'records_changed' => (int) ($result['updated'] ?? 0),
            'message' => 'Detalhes sincronizados em lote.',
            'meta' => [
                'status' => ((int) ($result['errors'] ?? 0)) === 0 ? 'success' : 'partial',
                'competition_code' => $ctx['code'],
                'season' => $ctx['season'],
                'stage' => $ctx['stage'],
                'apis_synced' => $apisSynced,
                'sync_type' => $requestedSyncType,
                'sync_mode' => (string) ($result['sync_mode'] ?? 'batch'),
                'api_football_sync_type' => $apiFootballSyncType,
                'data_source' => 'database_only',
                'api_request_counts' => [
                    'football_data' => max(0, (int) ($result['selected'] ?? 0)),
                    'api_football' => max(0, $apiFootballRequests),
                ],
                'api_failure_counts' => [
                    'api_football' => max(0, $apiFootballFailures),
                ],
                'details_enriched' => (int) ($result['enriched'] ?? 0),
            ],
            'synced_at' => now(),
        ]);

        if ($apiFootballRequests > 0) {
            ApiSyncLog::create([
                'provider' => 'api_football',
                'endpoint' => '/fixtures',
                'http_status' => $apiFootballFailures > 0 ? 207 : 200,
                'success' => $apiFootballFailures === 0,
                'records_total' => $apiFootballRequests,
                'records_changed' => (int) ($result['enriched'] ?? 0),
                'message' => $apiFootballFailures > 0
                    ? 'API-Football sincronizou com falhas parciais.'
                    : 'API-Football sincronizou com sucesso.',
                'meta' => [
                    'status' => $apiFootballFailures > 0 ? 'partial' : 'success',
                    'competition_code' => $ctx['code'],
                    'season' => $ctx['season'],
                    'stage' => $ctx['stage'],
                    'apis_synced' => ['api_football'],
                    'sync_type' => $requestedSyncType,
                    'sync_mode' => (string) ($result['sync_mode'] ?? 'batch'),
                    'api_football_sync_type' => $apiFootballSyncType,
                    'data_source' => 'database_only',
                    'api_request_counts' => [
                        'api_football' => max(0, $apiFootballRequests),
                    ],
                    'api_failure_counts' => [
                        'api_football' => max(0, $apiFootballFailures),
                    ],
                ],
                'synced_at' => now(),
            ]);
        }

        $this->info(sprintf(
            'Detalhes sincronizados (%s/%d). Selecionados: %d | Atualizados: %d | Erros: %d | API-Football req: %d (falhas: %d) | Standings: %s',
            $ctx['code'],
            $ctx['season'],
            $result['selected'],
            $result['updated'],
            $result['errors'],
            $apiFootballRequests,
            $apiFootballFailures,
            $standingsInfo
        ));

        return self::SUCCESS;
    }

    private function syncStandingsIfDue(
        FootballDataClient $client,
        SyncWorldCupStandingsService $standingsSyncService,
        string $code,
        int $season,
        ?string $stage
    ): string {
        $cooldownMinutes = 5;
        $cacheKey = sprintf('competition:%s:%d:standings:last_sync_at', $code, $season);
        $lastSyncAt = Cache::get($cacheKey);

        if ($lastSyncAt && now()->diffInMinutes($lastSyncAt) < $cooldownMinutes) {
            return 'ignorado (cooldown)';
        }

        try {
            $payload = $client->competitionStandings($code, $season);
            $groups = $standingsSyncService->sync($payload, $season, $stage);
            Cache::put($cacheKey, now(), now()->addMinutes($cooldownMinutes + 1));

            return $groups.' grupo(s)';
        } catch (Throwable $e) {
            report($e);

            return 'falha';
        }
    }
}
