<?php

namespace App\Livewire\Admin;

use App\Jobs\CalculatePredictionsForMatchJob;
use App\Jobs\RecalculatePoolRankingsJob;
use App\Models\ApiSyncLog;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Services\FootballData\FootballDataClient;
use App\Services\FootballData\SyncWorldCupMatchDetailsService;
use App\Services\FootballData\SyncWorldCupMatchesService;
use App\Services\FootballData\SyncWorldCupStandingsService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class ApiSyncDashboard extends Component
{
    use WithPagination;

    public bool $syncing = false;
    public string $syncMessage = '';
    public bool $syncSuccess = false;

    public function mount(): void
    {
        $this->assertAdmin();
    }

    public function triggerSync(
        FootballDataClient $client,
        SyncWorldCupMatchesService $syncService,
        SyncWorldCupStandingsService $standingsSyncService,
        SyncWorldCupMatchDetailsService $detailsSyncService
    ): void
    {
        $this->assertAdmin();

        $this->syncing = true;
        $this->syncMessage = '';

        try {
            $competitions = $this->competitionsToSync();
            $competitionCount = max(1, count($competitions));
            $rpmCap = max(1, (int) config('football-data.rate_limit.free_requests_per_minute', 10));
            $configuredDetailsLimit = max(1, (int) config('football-data.match_details.sync_limit_per_minute', 8));
            // Orçamento por competição: 2 chamadas fixas (matches + standings) + N detalhes.
            $safeDetailsLimit = max(1, min(
                $configuredDetailsLimit,
                (int) floor($rpmCap / $competitionCount) - 2
            ));
            $totalMatches = 0;
            $totalChanged = 0;
            $totalStandings = 0;
            $totalDetailsUpdated = 0;
            $totalDetailsEnriched = 0;
            $summaryParts = [];

            foreach ($competitions as $ctx) {
                $payload = $client->competitionMatches($ctx['code'], $ctx['season'], $ctx['stage']);
                $changed = $syncService->sync($payload, $ctx['season']);
                $standingsPayload = $client->competitionStandings($ctx['code'], $ctx['season']);
                $standingsCount = $standingsSyncService->sync($standingsPayload, $ctx['season'], $ctx['stage']);
                $detailsResult = $detailsSyncService->syncBatch(
                    $safeDetailsLimit,
                    $ctx['code'],
                    $ctx['season'],
                    $ctx['stage']
                );

                foreach ($changed as $match) {
                    if ($match->status === 'FINISHED') {
                        CalculatePredictionsForMatchJob::dispatch($match->id);
                    }
                }

                $recordsTotal = count($payload['matches'] ?? []);
                $recordsChanged = $changed->count();
                $totalMatches += $recordsTotal;
                $totalChanged += $recordsChanged;
                $totalStandings += $standingsCount;
                $totalDetailsUpdated += (int) ($detailsResult['updated'] ?? 0);
                $totalDetailsEnriched += (int) ($detailsResult['enriched'] ?? 0);

                ApiSyncLog::create([
                    'provider' => 'football_data',
                    'endpoint' => '/competitions/'.$ctx['code'].'/matches',
                    'http_status' => 200,
                    'success' => true,
                    'records_total' => $recordsTotal,
                    'records_changed' => $recordsChanged,
                    'message' => 'Sync manual via painel admin.',
                    'meta' => [
                        'competition_code' => $ctx['code'],
                        'season' => $ctx['season'],
                        'stage' => $ctx['stage'],
                        'apis_synced' => $this->resolveApisSynced($detailsResult),
                        'sync_type' => 'manual_admin',
                        'sync_mode' => (string) ($detailsResult['sync_mode'] ?? 'batch'),
                        'api_football_sync_type' => (string) ($detailsResult['api_football_sync_type'] ?? 'not_used'),
                        'data_source' => 'database_only',
                        'standings_synced' => $standingsCount,
                        'details_updated' => (int) ($detailsResult['updated'] ?? 0),
                        'details_enriched' => (int) ($detailsResult['enriched'] ?? 0),
                    ],
                    'synced_at' => now(),
                ]);

                $summaryParts[] = sprintf(
                    '%s/%d: %d alterados, %d grupos, %d detalhes (limite=%d)',
                    $ctx['code'],
                    $ctx['season'],
                    $recordsChanged,
                    $standingsCount,
                    (int) ($detailsResult['updated'] ?? 0),
                    $safeDetailsLimit
                );
            }

            Pool::query()->pluck('id')->each(fn (int $id) => RecalculatePoolRankingsJob::dispatch($id));

            $this->syncSuccess = true;
            $this->syncMessage = 'Sync concluído (todas as competições ativas). '
                ."Jogos alterados: {$totalChanged}/{$totalMatches}. "
                ."Grupos sincronizados: {$totalStandings}. "
                ."Detalhes atualizados: {$totalDetailsUpdated}. Enriquecidos (lineup/stats): {$totalDetailsEnriched}. "
                .'['.implode(' | ', $summaryParts).']';
        } catch (Throwable $e) {
            ApiSyncLog::create([
                'provider' => 'football_data',
                'endpoint' => '/competitions/*/matches',
                'success' => false,
                'message' => 'Falha na sincronizacao manual via painel admin.',
                'meta' => [
                    'apis_synced' => ['football_data'],
                    'sync_type' => 'manual_admin',
                    'sync_mode' => 'batch',
                    'data_source' => 'database_only',
                    'exception' => get_class($e),
                    'error' => $e->getMessage(),
                ],
                'synced_at' => now(),
            ]);

            $this->syncSuccess = false;
            $this->syncMessage = 'Erro ao sincronizar. Verifique token/configuracoes e tente novamente.';
        } finally {
            $this->syncing = false;
        }
    }

    public function render()
    {
        $this->assertAdmin();

        $logsQuery = ApiSyncLog::query()
            ->where(function ($q): void {
                $q->whereNull('meta->status')
                    ->orWhere('meta->status', '!=', 'skipped');
            });

        $logs = (clone $logsQuery)
            ->orderByDesc('synced_at')
            ->paginate(15);

        $lastSuccess = ApiSyncLog::query()
            ->where('success', true)
            ->orderByDesc('synced_at')
            ->first();

        $apiRequestChart = $this->buildApiRequestChart(
            (clone $logsQuery)->latest('synced_at')->limit(200)->get()
        );
        $apiHourlyVolume = $this->buildApiHourlyVolume(
            (clone $logsQuery)->where('synced_at', '>=', now()->subDay())->get()
        );

        $totalMatches = FootballMatch::count();
        $liveMatches  = FootballMatch::whereIn('status', ['IN_PLAY', 'PAUSED'])->count();
        $totalSyncs   = ApiSyncLog::count();
        $failedSyncs  = ApiSyncLog::where('success', false)
            ->where('synced_at', '>=', now()->subDay())
            ->count();

        return view('livewire.admin.apisyncdashboard', compact(
            'logs', 'lastSuccess', 'totalMatches', 'liveMatches', 'totalSyncs', 'failedSyncs', 'apiRequestChart', 'apiHourlyVolume'
        ));
    }

    private function assertAdmin(): void
    {
        abort_unless((bool) Auth::user()?->is_admin, 403);
    }

    /**
     * @return array<int, array{code:string,season:int,stage:string}>
     */
    private function competitionsToSync(): array
    {
        $all = (array) config('football-data.competitions', []);
        $items = [];

        foreach ($all as $key => $competition) {
            $code = strtoupper((string) ($competition['code'] ?? $key));

            $season = (int) ($competition['season'] ?? config('football-data.world_cup.season', 2026));
            $stage = (string) ($competition['default_stage'] ?? config('football-data.world_cup.stage', 'GROUP_STAGE'));

            if ($season <= 0 || $stage === '') {
                continue;
            }

            $items[] = [
                'code' => $code,
                'season' => $season,
                'stage' => $stage,
            ];
        }

        usort($items, fn ($a, $b) => strcmp($a['code'], $b['code']));

        return $items;
    }

    /**
     * @param array<string, mixed> $detailsResult
     * @return array<int, string>
     */
    private function resolveApisSynced(array $detailsResult): array
    {
        $apis = ['football_data'];

        if (((int) ($detailsResult['enriched'] ?? 0)) > 0) {
            $apis[] = 'api_football';
        }

        return $apis;
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\ApiSyncLog> $logs
     * @return array<int, array{api:string,total:int,success:int,failed:int}>
     */
    private function buildApiRequestChart(\Illuminate\Support\Collection $logs): array
    {
        $stats = [];

        foreach ($logs as $log) {
            $requestCounts = $this->extractApiRequestCounts($log);

            foreach ($requestCounts as $api => $count) {
                $name = (string) $api;
                if ($name === '') {
                    $name = 'unknown';
                }

                if (! isset($stats[$name])) {
                    $stats[$name] = [
                        'api' => $name,
                        'total' => 0,
                        'success' => 0,
                        'failed' => 0,
                    ];
                }

                $safeCount = max(0, (int) $count);
                $stats[$name]['total'] += $safeCount;
                if ($log->success) {
                    $stats[$name]['success'] += $safeCount;
                } else {
                    $stats[$name]['failed'] += $safeCount;
                }
            }
        }

        usort($stats, fn (array $a, array $b) => $b['total'] <=> $a['total']);

        return array_values($stats);
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\ApiSyncLog> $logs
     * @return array{labels:array<int,string>,series:array<int,array{api:string,values:array<int,int>}>}
     */
    private function buildApiHourlyVolume(\Illuminate\Support\Collection $logs): array
    {
        $timezone = 'America/Sao_Paulo';
        $labels = [];
        $hourKeys = [];

        for ($i = 23; $i >= 0; $i--) {
            $hour = now($timezone)->subHours($i)->startOfHour();
            $key = $hour->format('Y-m-d H:00:00');
            $hourKeys[] = $key;
            $labels[] = $hour->format('H:i');
        }

        $seriesMap = [];

        foreach ($logs as $log) {
            $syncedAt = $log->synced_at?->copy()->timezone($timezone)->startOfHour();
            if (! $syncedAt) {
                continue;
            }

            $hourKey = $syncedAt->format('Y-m-d H:00:00');
            if (! in_array($hourKey, $hourKeys, true)) {
                continue;
            }

            $requestCounts = $this->extractApiRequestCounts($log);

            foreach ($requestCounts as $api => $count) {
                $name = (string) $api;
                if ($name === '') {
                    $name = 'unknown';
                }

                if (! isset($seriesMap[$name])) {
                    $seriesMap[$name] = array_fill(0, count($hourKeys), 0);
                }

                $index = array_search($hourKey, $hourKeys, true);
                if ($index !== false) {
                    $seriesMap[$name][$index] += max(0, (int) $count);
                }
            }
        }

        $series = [];
        foreach ($seriesMap as $api => $values) {
            $series[] = ['api' => $api, 'values' => $values];
        }

        usort($series, fn (array $a, array $b) => array_sum($b['values']) <=> array_sum($a['values']));

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * @return array<string, int>
     */
    private function extractApiRequestCounts(\App\Models\ApiSyncLog $log): array
    {
        $counts = (array) data_get($log->meta, 'api_request_counts', []);
        if ($counts !== []) {
            $normalized = [];
            foreach ($counts as $api => $count) {
                $name = (string) $api;
                if ($name === '') {
                    continue;
                }
                $normalized[$name] = max(0, (int) $count);
            }

            if ($normalized !== []) {
                return $normalized;
            }
        }

        $apis = (array) data_get($log->meta, 'apis_synced', [$log->provider ?: 'unknown']);
        $apis = $apis !== [] ? $apis : [$log->provider ?: 'unknown'];
        $fallback = [];
        foreach ($apis as $api) {
            $name = (string) $api;
            if ($name === '') {
                $name = 'unknown';
            }
            $fallback[$name] = 1;
        }

        return $fallback;
    }
}
