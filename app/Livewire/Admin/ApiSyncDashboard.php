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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class ApiSyncDashboard extends Component
{
    use WithPagination;

    public bool $syncing = false;
    public bool $consolidating = false;
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
            $baseDetailsLimit = max(1, min(
                $configuredDetailsLimit,
                (int) floor($rpmCap / $competitionCount) - 2
            ));
            $totalMatches = 0;
            $totalChanged = 0;
            $totalStandings = 0;
            $totalDetailsUpdated = 0;
            $totalDetailsEnriched = 0;
            $summaryParts = [];
            $failedParts = [];
            $successfulCompetitions = 0;

            foreach ($competitions as $ctx) {
                try {
                    $payload = $client->competitionMatches($ctx['code'], $ctx['season'], $ctx['stage']);
                    $changed = $syncService->sync($payload, $ctx['season']);
                    $standingsPayload = $client->competitionStandings($ctx['code'], $ctx['season']);
                    $standingsCount = $standingsSyncService->sync($standingsPayload, $ctx['season'], $ctx['stage']);

                    $liveCount = $this->liveMatchCountForCompetition($ctx['code'], $ctx['season']);
                    $detailsLimit = $liveCount > 0
                        ? max($baseDetailsLimit, min(60, $liveCount + 6))
                        : $baseDetailsLimit;

                    $detailsResult = $detailsSyncService->syncBatch(
                        $detailsLimit,
                        $ctx['code'],
                        $ctx['season'],
                        $ctx['stage'],
                        'manual_admin'
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
                    $successfulCompetitions++;

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
                            'sync_type' => $liveCount > 0 ? 'manual_admin_live_priority' : 'manual_admin',
                            'sync_mode' => (string) ($detailsResult['sync_mode'] ?? 'batch'),
                            'api_football_sync_type' => (string) ($detailsResult['api_football_sync_type'] ?? 'not_used'),
                            'data_source' => 'database_only',
                            'standings_synced' => $standingsCount,
                            'details_updated' => (int) ($detailsResult['updated'] ?? 0),
                            'details_enriched' => (int) ($detailsResult['enriched'] ?? 0),
                            'live_matches_detected' => $liveCount,
                            'details_limit_used' => $detailsLimit,
                        ],
                        'synced_at' => now(),
                    ]);

                    $summaryParts[] = sprintf(
                        '%s/%d: %d alterados, %d grupos, %d detalhes (limite=%d, ao vivo=%d)',
                        $ctx['code'],
                        $ctx['season'],
                        $recordsChanged,
                        $standingsCount,
                        (int) ($detailsResult['updated'] ?? 0),
                        $detailsLimit,
                        $liveCount
                    );
                } catch (Throwable $e) {
                    ApiSyncLog::create([
                        'provider' => 'football_data',
                        'endpoint' => '/competitions/'.$ctx['code'].'/matches',
                        'success' => false,
                        'message' => 'Falha parcial na sincronizacao manual via painel admin.',
                        'meta' => [
                            'competition_code' => $ctx['code'],
                            'season' => $ctx['season'],
                            'stage' => $ctx['stage'],
                            'apis_synced' => ['football_data'],
                            'sync_type' => 'manual_admin',
                            'sync_mode' => 'batch',
                            'data_source' => 'database_only',
                            'exception' => get_class($e),
                            'error' => $e->getMessage(),
                        ],
                        'synced_at' => now(),
                    ]);

                    $failedParts[] = sprintf('%s/%d: %s', $ctx['code'], $ctx['season'], Str::limit($e->getMessage(), 120));
                }
            }

            if ($successfulCompetitions > 0) {
                Pool::query()->pluck('id')->each(fn (int $id) => RecalculatePoolRankingsJob::dispatch($id));
            }

            $this->syncSuccess = $successfulCompetitions > 0 && $failedParts === [];
            $statusLabel = $failedParts === [] ? 'Sync concluído (todas as competições ativas).' : 'Sync concluído com falhas parciais.';
            $this->syncMessage = $statusLabel.' '
                ."Competições OK: {$successfulCompetitions}/{$competitionCount}. "
                ."Jogos alterados: {$totalChanged}/{$totalMatches}. "
                ."Grupos sincronizados: {$totalStandings}. "
                ."Detalhes atualizados: {$totalDetailsUpdated}. Enriquecidos (lineup/stats): {$totalDetailsEnriched}. "
                .'['.implode(' | ', $summaryParts).']'
                .($failedParts === [] ? '' : ' Falhas: ['.implode(' | ', $failedParts).']');
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

        $syncLogsQuery = ApiSyncLog::query()
            ->where('is_request_log', false)
            ->where(function ($q): void {
                $q->whereNull('meta->status')
                    ->orWhere('meta->status', '!=', 'skipped');
            });

        $requestLogsQuery = ApiSyncLog::query()
            ->where('is_request_log', true)
            ->whereIn('provider', ['football_data', 'api_football']);

        $logs = (clone $syncLogsQuery)
            ->orderByDesc('synced_at')
            ->paginate(15);

        $lastSuccess = ApiSyncLog::query()
            ->where('success', true)
            ->where('is_request_log', false)
            ->orderByDesc('synced_at')
            ->first();

        $apiRequestChart = $this->buildApiRequestChartFromRequests(
            (clone $requestLogsQuery)->where('synced_at', '>=', now()->subDay())->get()
        );
        $apiHourlyVolume = $this->buildApiHourlyVolume(
            (clone $requestLogsQuery)->where('synced_at', '>=', now()->subDay())->get()
        );
        $apiUsageSummary = $this->buildApiUsageSummary($requestLogsQuery);

        $totalMatches = FootballMatch::count();
        $liveMatches  = FootballMatch::whereIn('status', ['IN_PLAY', 'PAUSED'])->count();
        $totalSyncs   = ApiSyncLog::where('is_request_log', false)->count();
        $failedSyncs  = ApiSyncLog::where('success', false)
            ->where('is_request_log', false)
            ->where('synced_at', '>=', now()->subDay())
            ->count();

        return view('livewire.admin.apisyncdashboard', compact(
            'logs',
            'lastSuccess',
            'totalMatches',
            'liveMatches',
            'totalSyncs',
            'failedSyncs',
            'apiRequestChart',
            'apiHourlyVolume',
            'apiUsageSummary'
        ));
    }

    public function triggerConsolidation(): void
    {
        $this->assertAdmin();

        $this->consolidating = true;
        $this->syncMessage = '';

        try {
            $exitCode = Artisan::call('sports:consolidate-daily-results', [
                '--timezone' => 'America/Sao_Paulo',
            ]);

            $output = trim((string) Artisan::output());
            $this->syncSuccess = $exitCode === 0;
            $this->syncMessage = $exitCode === 0
                ? 'Consolidação manual executada com sucesso.'.($output !== '' ? ' '.$output : '')
                : 'Falha ao executar consolidação manual.'.($output !== '' ? ' '.$output : '');
        } catch (Throwable $e) {
            $this->syncSuccess = false;
            $this->syncMessage = 'Erro ao executar consolidação manual: '.$e->getMessage();
        } finally {
            $this->consolidating = false;
        }
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

    private function liveMatchCountForCompetition(string $competitionCode, int $seasonYear): int
    {
        return FootballMatch::query()
            ->whereHas('competition', fn ($q) => $q->where('code', strtoupper($competitionCode)))
            ->whereHas('season', fn ($q) => $q->where('year', $seasonYear))
            ->whereIn('status', ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'])
            ->count();
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\ApiSyncLog> $logs
     * @return array<int, array{api:string,total:int,success:int,failed:int}>
     */
    private function buildApiRequestChartFromRequests(\Illuminate\Support\Collection $logs): array
    {
        $stats = [];

        foreach ($logs as $log) {
            $name = (string) ($log->provider ?: 'unknown');
            if (! isset($stats[$name])) {
                $stats[$name] = [
                    'api' => $name,
                    'total' => 0,
                    'success' => 0,
                    'failed' => 0,
                ];
            }

            $stats[$name]['total'] += 1;
            if ($log->success) {
                $stats[$name]['success'] += 1;
            } else {
                $stats[$name]['failed'] += 1;
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

            $name = (string) ($log->provider ?: 'unknown');
            if ($name === '') {
                $name = 'unknown';
            }

            if (! isset($seriesMap[$name])) {
                $seriesMap[$name] = array_fill(0, count($hourKeys), 0);
            }

            $index = array_search($hourKey, $hourKeys, true);
            if ($index !== false) {
                $seriesMap[$name][$index] += 1;
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
     * @param \Illuminate\Database\Eloquent\Builder<\App\Models\ApiSyncLog> $requestLogsQuery
     * @return array<int, array{api:string,req_24h:int,req_month:int,peak_rpm:int,peak_minute:string,avg_latency_ms:float}>
     */
    private function buildApiUsageSummary(\Illuminate\Database\Eloquent\Builder $requestLogsQuery): array
    {
        $providers = ['football_data', 'api_football'];
        $summary = [];

        foreach ($providers as $provider) {
            $base = (clone $requestLogsQuery)->where('provider', $provider);
            $req24h = (clone $base)->where('synced_at', '>=', now()->subDay())->count();
            $reqMonth = (clone $base)->where('synced_at', '>=', now()->startOfMonth())->count();
            $avgLatency = (float) ((clone $base)->whereNotNull('duration_ms')->avg('duration_ms') ?? 0.0);

            $peak = (clone $base)
                ->selectRaw("DATE_FORMAT(synced_at, '%Y-%m-%d %H:%i:00') as minute_slot, COUNT(*) as c")
                ->groupBy('minute_slot')
                ->orderByDesc('c')
                ->orderByDesc('minute_slot')
                ->first();

            $summary[] = [
                'api' => $provider,
                'req_24h' => $req24h,
                'req_month' => $reqMonth,
                'peak_rpm' => (int) ($peak->c ?? 0),
                'peak_minute' => (string) ($peak->minute_slot ?? '—'),
                'avg_latency_ms' => round($avgLatency, 1),
            ];
        }

        return $summary;
    }
}
