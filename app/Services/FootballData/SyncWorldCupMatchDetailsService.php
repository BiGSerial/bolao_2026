<?php

namespace App\Services\FootballData;

use App\Events\MatchUpdated;
use App\Events\MatchDetailUpdated;
use App\Models\FootballMatch;
use App\Models\FootballMatchDetail;
use App\Models\MatchProviderRef;
use App\Models\TeamProviderRef;
use App\Services\Api\Connectors\ApiFootballConnector;
use App\Services\Api\Connectors\FootballDataConnector;
use App\Services\FootballData\Projections\ApiFootballDetailProjectionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncWorldCupMatchDetailsService
{
    private string $apiFootballSyncType = 'not_used';

    public function __construct(
        private readonly FootballDataConnector $footballDataConnector,
        private readonly ApiFootballConnector $apiFootballConnector,
        private readonly ApiFootballDetailProjectionService $projectionService,
    ) {
    }

    public function syncBatch(int $limit = 8, ?string $competitionCode = null, ?int $seasonYear = null, ?string $stage = null): array
    {
        $this->apiFootballConnector->resetMetrics();
        $this->apiFootballSyncType = 'not_used';

        $matches = $this->matchesToSync($limit, $competitionCode, $seasonYear, $stage)
            ->loadMissing(['homeTeam:id,name,short_name,tla', 'awayTeam:id,name,short_name,tla']);

        $updated = 0;
        $errors = 0;
        $enriched = 0;

        $footballDataDetails = $this->footballDataConnector->fetchDetailsBatch($matches);
        $apiFootballIndex = $this->buildApiFootballEnrichmentIndex($matches, $competitionCode, $seasonYear);

        foreach ($matches as $match) {
            $matchId = (int) $match->id;

            try {
                $footballDataPayload = $footballDataDetails[$matchId]['payload'] ?? null;
                if (! is_array($footballDataPayload) || $footballDataPayload === []) {
                    throw new \RuntimeException($footballDataDetails[$matchId]['error'] ?? 'Falha ao obter detalhe no provider base.');
                }

                $existing = FootballMatchDetail::query()->where('football_match_id', $matchId)->first();
                $before = $existing ? md5(json_encode($existing->payload)) : null;

                $apiFootballPayload = $apiFootballIndex[$matchId] ?? null;
                if (is_array($apiFootballPayload)) {
                    $this->upsertApiFootballTeamRefs($match, $apiFootballPayload);
                    $this->syncMatchStateFromApiFootball($match, $apiFootballPayload);
                }

                $mergedPayload = $this->mergePayloads($footballDataPayload, $apiFootballPayload);
                $after = md5(json_encode($mergedPayload));

                FootballMatchDetail::updateOrCreate(
                    ['football_match_id' => $matchId],
                    [
                        'provider' => $apiFootballPayload ? 'multi' : 'football_data',
                        'external_id' => (int) $match->external_id,
                        'payload' => $mergedPayload,
                        'fetched_at' => now(),
                        'last_error' => null,
                    ]
                );

                if (is_array($apiFootballPayload)) {
                    $fixtureId = (int) data_get($apiFootballPayload, 'fixture.id', 0);
                    if ($fixtureId > 0) {
                        MatchProviderRef::updateOrCreate(
                            ['provider' => 'api_football', 'external_id' => $fixtureId],
                            ['football_match_id' => $matchId]
                        );
                    }

                    if ((bool) config('api-integration.project_match_details', true)) {
                        $this->projectionService->project($match, $apiFootballPayload);
                    }
                }

                $updated++;
                if ($apiFootballPayload) {
                    $enriched++;
                }

                if ($before !== $after && in_array($match->status, ['IN_PLAY', 'PAUSED'], true)) {
                    MatchDetailUpdated::dispatch($match);
                }
            } catch (Throwable $e) {
                FootballMatchDetail::updateOrCreate(
                    ['football_match_id' => $matchId],
                    [
                        'provider' => 'football_data',
                        'external_id' => (int) $match->external_id,
                        'last_error' => $e->getMessage(),
                    ]
                );
                $errors++;
            }
        }

        $apiFootballMetrics = $this->apiFootballConnector->metrics();

        return [
            'selected' => $matches->count(),
            'updated' => $updated,
            'errors' => $errors,
            'enriched' => $enriched,
            'api_football_requests' => (int) ($apiFootballMetrics['requests'] ?? 0),
            'api_football_failures' => (int) ($apiFootballMetrics['failures'] ?? 0),
            'api_football_sync_type' => $this->apiFootballSyncType,
            'sync_mode' => 'batch',
        ];
    }

    /**
     * @return array<int, array>
     */
    private function buildApiFootballEnrichmentIndex(Collection $matches, ?string $competitionCode, ?int $seasonYear): array
    {
        if (! (bool) config('api-football.enabled', true)) {
            $this->apiFootballSyncType = 'disabled';
            return [];
        }

        if ((string) config('api-football.token', '') === '') {
            $this->apiFootballSyncType = 'missing_token';
            return [];
        }

        $code = strtoupper((string) ($competitionCode ?: ''));
        if ($code === '') {
            return [];
        }

        $competitionCfg = (array) config('api-football.competitions.'.$code, []);
        $leagueId = (int) ($competitionCfg['league_id'] ?? 0);
        $season = (int) ($seasonYear ?: ($competitionCfg['season'] ?? 0));

        if ($leagueId <= 0 || $season <= 0) {
            $this->apiFootballSyncType = 'competition_not_mapped';
            return [];
        }

        if (! $this->canUseApiFootballForCurrentBatch($matches)) {
            return [];
        }

        $fixtureIdByMatch = [];

        foreach ($matches as $match) {
            $existingRef = MatchProviderRef::query()
                ->where('football_match_id', (int) $match->id)
                ->where('provider', 'api_football')
                ->first();

            if ($existingRef) {
                $fixtureIdByMatch[(int) $match->id] = (int) $existingRef->external_id;
            }
        }

        $unresolved = $matches->filter(fn (FootballMatch $m) => ! isset($fixtureIdByMatch[(int) $m->id]))->values();
        if ($unresolved->isNotEmpty()) {
            try {
                $resolved = $this->apiFootballConnector->resolveFixtureIds($unresolved, $leagueId, $season);
                foreach ($resolved as $matchId => $fixtureId) {
                    $fixtureIdByMatch[$matchId] = $fixtureId;
                    MatchProviderRef::updateOrCreate(
                        ['provider' => 'api_football', 'external_id' => $fixtureId],
                        ['football_match_id' => $matchId]
                    );
                }
            } catch (Throwable) {
                // falha parcial tolerada
            }
        }

        if ($fixtureIdByMatch === []) {
            return [];
        }

        try {
            $detailByFixtureId = $this->apiFootballConnector->fetchFixtureDetailsByIds(array_values($fixtureIdByMatch));
        } catch (Throwable) {
            return [];
        }

        $matchIndex = [];
        foreach ($fixtureIdByMatch as $matchId => $fixtureId) {
            if (isset($detailByFixtureId[$fixtureId])) {
                $matchIndex[$matchId] = $detailByFixtureId[$fixtureId];
            }
        }

        return $matchIndex;
    }

    /**
     * Fora de jogos ao vivo, API-Football atua apenas como complemento (máx. 30 atualizações/dia).
     */
    private function canUseApiFootballForCurrentBatch(Collection $matches): bool
    {
        $hasLiveMatches = $matches->contains(function (FootballMatch $match): bool {
            return in_array((string) $match->status, ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'], true);
        });

        if ($hasLiveMatches) {
            $this->apiFootballSyncType = 'live_priority';

            return true;
        }

        $maxDailyComplementaryUpdates = max(0, (int) config('api-football.complementary.max_daily_updates_outside_live', 30));
        if ($maxDailyComplementaryUpdates === 0) {
            $this->apiFootballSyncType = 'complementary_disabled';

            return false;
        }

        $dayKey = now('America/Sao_Paulo')->format('Ymd');
        $counterKey = 'api-football:complementary:daily-updates:'.$dayKey;
        $lock = Cache::lock($counterKey.':lock', 5);
        $acquired = $lock->block(3);

        if (! $acquired) {
            $this->apiFootballSyncType = 'complementary_lock_timeout';

            return false;
        }

        try {
            $currentCount = (int) Cache::get($counterKey, 0);
            if ($currentCount >= $maxDailyComplementaryUpdates) {
                $this->apiFootballSyncType = 'complementary_daily_cap_reached';

                return false;
            }

            Cache::put($counterKey, $currentCount + 1, now('America/Sao_Paulo')->endOfDay()->addHour());
            $this->apiFootballSyncType = 'complementary_off_live';

            return true;
        } finally {
            optional($lock)->release();
        }
    }

    private function syncMatchStateFromApiFootball(FootballMatch $match, array $apiFootballPayload): void
    {
        $apiStatus = strtoupper((string) data_get($apiFootballPayload, 'fixture.status.short', ''));
        $mappedStatus = $this->mapApiFootballStatus($apiStatus);
        if ($mappedStatus === null) {
            return;
        }

        $before = [
            'status' => (string) $match->status,
            'utc_date' => optional($match->utc_date)?->format('Y-m-d H:i:s'),
            'minute' => data_get($match->raw_payload, 'minute'),
        ];

        $attributes = [
            'status' => $mappedStatus,
            'raw_payload' => $this->mergeRawPayloadFromApiFootball($match, $apiFootballPayload),
            'last_updated_by_provider_at' => now()->utc(),
        ];

        $fixtureDate = data_get($apiFootballPayload, 'fixture.date');
        if (is_string($fixtureDate) && $fixtureDate !== '') {
            try {
                $kickoffUtc = \Carbon\Carbon::parse($fixtureDate)->utc();
                $attributes['utc_date'] = $kickoffUtc;
                $attributes['local_date'] = $kickoffUtc->copy()->timezone('America/Sao_Paulo');
            } catch (Throwable) {
                // ignora data inválida sem quebrar o fluxo.
            }
        }

        $nowUtc = now()->utc();
        $previousStatus = (string) $match->status;
        $accumulated = (int) ($match->live_clock_accumulated_seconds ?? 0);
        $anchor = $match->live_clock_anchor_at;

        if ($previousStatus === 'IN_PLAY' && $mappedStatus !== 'IN_PLAY' && $anchor) {
            $accumulated += max(0, $anchor->diffInSeconds($nowUtc));
            $attributes['live_clock_accumulated_seconds'] = $accumulated;
            $attributes['live_clock_anchor_at'] = null;
        } else {
            $attributes['live_clock_accumulated_seconds'] = $accumulated;
            $attributes['live_clock_anchor_at'] = $anchor;
        }

        if ($mappedStatus === 'IN_PLAY' && $previousStatus !== 'IN_PLAY') {
            $attributes['in_play_started_at'] = $match->in_play_started_at ?? $nowUtc;
            $attributes['live_clock_anchor_at'] = $nowUtc;
        }

        if ($mappedStatus === 'PAUSED' && $previousStatus !== 'PAUSED') {
            $attributes['interval_started_at'] = $nowUtc;
        }

        if ($mappedStatus === 'FINISHED' && $previousStatus !== 'FINISHED') {
            $attributes['finished_at'] = $nowUtc;
        }

        $match->fill($attributes);
        $isDirty = $match->isDirty(['status', 'utc_date', 'local_date', 'raw_payload', 'live_clock_anchor_at', 'live_clock_accumulated_seconds']);
        if (! $isDirty) {
            return;
        }

        $match->save();

        $after = [
            'status' => (string) $match->status,
            'utc_date' => optional($match->utc_date)?->format('Y-m-d H:i:s'),
            'minute' => data_get($match->raw_payload, 'minute'),
        ];

        if ($before !== $after) {
            MatchUpdated::dispatch($match);
        }
    }

    private function mapApiFootballStatus(string $status): ?string
    {
        return match ($status) {
            '1H', '2H', 'ET', 'BT', 'P' => 'IN_PLAY',
            'HT' => 'PAUSED',
            'FT', 'AET', 'PEN' => 'FINISHED',
            'NS', 'TBD' => 'TIMED',
            'PST' => 'POSTPONED',
            'CANC', 'ABD', 'AWD', 'WO' => 'CANCELLED',
            'SUSP', 'INT' => 'PAUSED',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeRawPayloadFromApiFootball(FootballMatch $match, array $apiFootballPayload): array
    {
        $raw = (array) ($match->raw_payload ?? []);
        $minute = data_get($apiFootballPayload, 'fixture.status.elapsed');

        if (is_numeric($minute)) {
            $raw['minute'] = (int) $minute;
        } else {
            unset($raw['minute']);
        }

        $raw['api_football_status'] = [
            'short' => strtoupper((string) data_get($apiFootballPayload, 'fixture.status.short', '')),
            'long' => (string) data_get($apiFootballPayload, 'fixture.status.long', ''),
            'elapsed' => is_numeric($minute) ? (int) $minute : null,
        ];

        return $raw;
    }

    private function upsertApiFootballTeamRefs(FootballMatch $match, array $apiFootballPayload): void
    {
        $homeApiId = (int) data_get($apiFootballPayload, 'teams.home.id', 0);
        $awayApiId = (int) data_get($apiFootballPayload, 'teams.away.id', 0);

        if ($homeApiId > 0 && $match->home_team_id) {
            TeamProviderRef::updateOrCreate(
                ['provider' => 'api_football', 'external_id' => $homeApiId],
                ['team_id' => (int) $match->home_team_id]
            );
        }

        if ($awayApiId > 0 && $match->away_team_id) {
            TeamProviderRef::updateOrCreate(
                ['provider' => 'api_football', 'external_id' => $awayApiId],
                ['team_id' => (int) $match->away_team_id]
            );
        }
    }

    private function mergePayloads(array $footballDataPayload, ?array $apiFootballPayload): array
    {
        if ($apiFootballPayload === null) {
            return $footballDataPayload;
        }

        $footballDataPayload['_providers'] = [
            'football_data' => [
                'external_id' => (int) data_get($footballDataPayload, 'match.id', 0),
            ],
            'api_football' => [
                'fixture_id' => (int) data_get($apiFootballPayload, 'fixture.id', 0),
                'has_lineups' => ! empty(data_get($apiFootballPayload, 'lineups')),
                'has_statistics' => ! empty(data_get($apiFootballPayload, 'statistics')),
                'has_players' => ! empty(data_get($apiFootballPayload, 'players')),
            ],
            'primary' => 'football_data',
        ];

        $footballDataPayload['_api_football'] = $apiFootballPayload;

        return $footballDataPayload;
    }

    /**
     * @return Collection<int, FootballMatch>
     */
    private function matchesToSync(int $limit, ?string $competitionCode = null, ?int $seasonYear = null, ?string $stage = null): Collection
    {
        $staleMinutes = (int) config('football-data.match_details.stale_minutes', 15);
        $backfillDays = max(1, (int) config('football-data.match_details.backfill_finished_days', 120));
        $safeLimit = max(1, $limit);

        $baseQuery = FootballMatch::query()
            ->when($competitionCode, fn ($q) => $q->whereHas('competition', fn ($qc) => $qc->where('code', strtoupper((string) $competitionCode))))
            ->when($seasonYear, fn ($q) => $q->whereHas('season', fn ($qs) => $qs->where('year', $seasonYear)))
            ->when($stage !== null && $stage !== '', fn ($q) => $q->where('stage', $stage));

        $backfill = (clone $baseQuery)
            ->where('status', 'FINISHED')
            ->whereBetween('utc_date', [now()->utc()->subDays($backfillDays), now()->utc()->subHours(3)])
            ->leftJoin('football_match_details', 'football_match_details.football_match_id', '=', 'football_matches.id')
            ->whereNull('football_match_details.id')
            ->orderByDesc('football_matches.utc_date')
            ->select('football_matches.*')
            ->limit($safeLimit)
            ->get();

        $remaining = $safeLimit - $backfill->count();
        if ($remaining <= 0) {
            return $backfill;
        }

        $priority = (clone $baseQuery)
            ->whereBetween('utc_date', [now()->utc()->subHours(3), now()->utc()->addHours(24)])
            ->whereIn('status', ['TIMED', 'SCHEDULED', 'IN_PLAY', 'PAUSED', 'FINISHED'])
            ->leftJoin('football_match_details', 'football_match_details.football_match_id', '=', 'football_matches.id')
            ->where(function ($q) use ($staleMinutes): void {
                $q->whereNull('football_match_details.fetched_at')
                    ->orWhere('football_match_details.fetched_at', '<', now()->subMinutes($staleMinutes));
            })
            ->orderByRaw("case football_matches.status when 'IN_PLAY' then 0 when 'PAUSED' then 1 when 'TIMED' then 2 when 'SCHEDULED' then 3 else 4 end")
            ->orderBy('football_matches.utc_date')
            ->select('football_matches.*')
            ->limit($remaining)
            ->get();

        return $backfill->concat($priority)
            ->unique(fn (FootballMatch $match) => (int) $match->id)
            ->values();
    }
}
