<?php

namespace App\Services\FootballData;

use App\Events\MatchUpdated;
use App\Events\MatchDetailUpdated;
use App\Models\FootballMatch;
use App\Models\FootballMatchDetail;
use App\Models\MatchProviderRef;
use App\Models\PoolMember;
use App\Models\Prediction;
use App\Models\TeamProviderRef;
use App\Jobs\SendWebPushToUsersJob;
use App\Services\Api\Connectors\ApiFootballConnector;
use App\Services\Api\Connectors\FootballDataConnector;
use App\Services\FootballData\Projections\ApiFootballDetailProjectionService;
use Illuminate\Support\Collection;
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

    public function syncBatch(
        int $limit = 8,
        ?string $competitionCode = null,
        ?int $seasonYear = null,
        ?string $stage = null,
        string $syncType = 'scheduled_auto'
    ): array
    {
        $this->apiFootballConnector->resetMetrics();
        $this->apiFootballSyncType = 'not_used';

        // live_score_quick: somente API paga — sem Football-Data, sem detail storage, sem projection.
        // Atualiza placar/status/live_clock e dispara MatchUpdated a cada minuto.
        if ($syncType === 'live_score_quick') {
            return $this->syncScoreQuick($limit, $competitionCode, $seasonYear, $stage);
        }

        // live_full_events: equivalente ao live_stats_refresh (API paga + gratuita + projection).
        if ($syncType === 'live_full_events') {
            $syncType = 'live_stats_refresh';
        }

        $matches = $this->matchesToSync($limit, $competitionCode, $seasonYear, $stage)
            ->loadMissing(['homeTeam:id,name,canonical_name_br,short_name,tla', 'awayTeam:id,name,canonical_name_br,short_name,tla']);

        $updated = 0;
        $errors = 0;
        $enriched = 0;
        $finishedChangedMatchIds = [];

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
                    if ($this->syncMatchStateFromApiFootball($match, $apiFootballPayload)) {
                        $finishedChangedMatchIds[] = $matchId;
                    }
                } elseif (in_array($match->status, ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'], true)) {
                    // Fallback durante ao vivo quando a API paga falha/indisponível.
                    if ($this->syncMatchStateFromFootballDataDetail($match, $footballDataPayload)) {
                        $finishedChangedMatchIds[] = $matchId;
                    }
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

                    $shouldProjectDetails = $syncType !== 'live_minute_tick';
                    if ($shouldProjectDetails && (bool) config('api-integration.project_match_details', true)) {
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
            'finished_changed_match_ids' => array_values(array_unique($finishedChangedMatchIds)),
            'sync_mode' => 'batch',
        ];
    }

    /**
     * Sync rápido de placar/status via API paga apenas.
     * Não consome Football-Data, não atualiza FootballMatchDetail, não roda projection.
     * Ideal para ciclos de 1 min durante partidas ao vivo.
     *
     * @return array<string, mixed>
     */
    private function syncScoreQuick(int $limit, ?string $competitionCode, ?int $seasonYear, ?string $stage): array
    {
        $matches = $this->matchesToSync($limit, $competitionCode, $seasonYear, $stage)
            ->loadMissing(['homeTeam:id,name,canonical_name_br,short_name,tla', 'awayTeam:id,name,canonical_name_br,short_name,tla']);

        $updated = 0;
        $errors  = 0;
        $finishedChangedMatchIds = [];

        if ($matches->isNotEmpty()) {
            $apiFootballIndex = $this->buildApiFootballEnrichmentIndex($matches, $competitionCode, $seasonYear);

            foreach ($matches as $match) {
                $matchId = (int) $match->id;
                try {
                    $payload = $apiFootballIndex[$matchId] ?? null;
                    if (! is_array($payload)) {
                        continue;
                    }

                    $this->upsertApiFootballTeamRefs($match, $payload);

                    if ($this->syncMatchStateFromApiFootball($match, $payload)) {
                        $finishedChangedMatchIds[] = $matchId;
                    }

                    $updated++;
                } catch (Throwable $e) {
                    $errors++;
                }
            }
        }

        $metrics = $this->apiFootballConnector->metrics();

        return [
            'selected'                    => $matches->count(),
            'updated'                     => $updated,
            'errors'                      => $errors,
            'enriched'                    => $updated,
            'api_football_requests'       => (int) ($metrics['requests'] ?? 0),
            'api_football_failures'       => (int) ($metrics['failures'] ?? 0),
            'api_football_sync_type'      => $this->apiFootballSyncType,
            'finished_changed_match_ids'  => array_values(array_unique($finishedChangedMatchIds)),
            'sync_mode'                   => 'score_quick',
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

    private function canUseApiFootballForCurrentBatch(Collection $matches): bool
    {
        if ($matches->isEmpty()) {
            $this->apiFootballSyncType = 'empty_batch';
            return false;
        }
        $this->apiFootballSyncType = 'always_on_non_empty_batch';
        return true;
    }

    private function syncMatchStateFromApiFootball(FootballMatch $match, array $apiFootballPayload): bool
    {
        $apiStatus = strtoupper((string) data_get($apiFootballPayload, 'fixture.status.short', ''));
        $mappedStatus = $this->mapApiFootballStatus($apiStatus);
        if ($mappedStatus === null) {
            return false;
        }

        $before = [
            'status' => (string) $match->status,
            'utc_date' => optional($match->utc_date)?->format('Y-m-d H:i:s'),
            'minute' => data_get($match->raw_payload, 'minute'),
            'home_score_full_time' => $match->home_score_full_time,
            'away_score_full_time' => $match->away_score_full_time,
        ];

        $attributes = [
            'status' => $mappedStatus,
            'raw_payload' => $this->mergeRawPayloadFromApiFootball($match, $apiFootballPayload),
            'last_updated_by_provider_at' => now()->utc(),
        ];

        $apiGoalsHome = data_get($apiFootballPayload, 'goals.home');
        $apiGoalsAway = data_get($apiFootballPayload, 'goals.away');
        if (is_numeric($apiGoalsHome) && is_numeric($apiGoalsAway)) {
            // A API paga é a fonte prioritária para placar de partida.
            $attributes['home_score_full_time'] = (int) $apiGoalsHome;
            $attributes['away_score_full_time'] = (int) $apiGoalsAway;
        }

        $apiHalfHome = data_get($apiFootballPayload, 'score.halftime.home');
        $apiHalfAway = data_get($apiFootballPayload, 'score.halftime.away');
        if (is_numeric($apiHalfHome) && is_numeric($apiHalfAway)) {
            $attributes['home_score_half_time'] = (int) $apiHalfHome;
            $attributes['away_score_half_time'] = (int) $apiHalfAway;
        }

        $apiExtraHome = data_get($apiFootballPayload, 'score.extratime.home');
        $apiExtraAway = data_get($apiFootballPayload, 'score.extratime.away');
        if (is_numeric($apiExtraHome) && is_numeric($apiExtraAway)) {
            $attributes['home_score_extra_time'] = (int) $apiExtraHome;
            $attributes['away_score_extra_time'] = (int) $apiExtraAway;
        }

        $apiPenHome = data_get($apiFootballPayload, 'score.penalty.home');
        $apiPenAway = data_get($apiFootballPayload, 'score.penalty.away');
        if (is_numeric($apiPenHome) && is_numeric($apiPenAway)) {
            $attributes['home_score_penalties'] = (int) $apiPenHome;
            $attributes['away_score_penalties'] = (int) $apiPenAway;
        }

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
        $isDirty = $match->isDirty([
            'status',
            'utc_date',
            'local_date',
            'raw_payload',
            'live_clock_anchor_at',
            'live_clock_accumulated_seconds',
            'home_score_full_time',
            'away_score_full_time',
            'home_score_half_time',
            'away_score_half_time',
            'home_score_extra_time',
            'away_score_extra_time',
            'home_score_penalties',
            'away_score_penalties',
        ]);
        if (! $isDirty) {
            return false;
        }

        $match->save();

        $after = [
            'status' => (string) $match->status,
            'utc_date' => optional($match->utc_date)?->format('Y-m-d H:i:s'),
            'minute' => data_get($match->raw_payload, 'minute'),
            'home_score_full_time' => $match->home_score_full_time,
            'away_score_full_time' => $match->away_score_full_time,
        ];

        if ($before !== $after) {
            MatchUpdated::dispatch($match);
            $this->dispatchMatchScorePush($match, $before, $after);
        }

        return (string) $match->status === 'FINISHED' && $before !== $after;
    }

    private function syncMatchStateFromFootballDataDetail(FootballMatch $match, array $footballDataPayload): bool
    {
        $incomingStatus = strtoupper((string) data_get($footballDataPayload, 'match.status', ''));
        $mappedStatus = $incomingStatus !== '' ? $incomingStatus : (string) $match->status;

        $score = (array) data_get($footballDataPayload, 'match.score', []);
        $homeFull = data_get($score, 'fullTime.home');
        $awayFull = data_get($score, 'fullTime.away');

        $before = [
            'status' => (string) $match->status,
            'home_score_full_time' => $match->home_score_full_time,
            'away_score_full_time' => $match->away_score_full_time,
        ];

        $match->fill([
            'status' => $mappedStatus,
            'home_score_full_time' => is_numeric($homeFull) ? (int) $homeFull : $match->home_score_full_time,
            'away_score_full_time' => is_numeric($awayFull) ? (int) $awayFull : $match->away_score_full_time,
            'last_updated_by_provider_at' => now()->utc(),
        ]);

        if (! $match->isDirty(['status', 'home_score_full_time', 'away_score_full_time'])) {
            return false;
        }

        $match->save();

        $after = [
            'status' => (string) $match->status,
            'home_score_full_time' => $match->home_score_full_time,
            'away_score_full_time' => $match->away_score_full_time,
        ];

        if ($before !== $after) {
            MatchUpdated::dispatch($match);
            $this->dispatchMatchScorePush($match, $before, $after);
        }

        return (string) $match->status === 'FINISHED' && $before !== $after;
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

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function dispatchMatchScorePush(FootballMatch $match, array $before, array $after): void
    {
        if (! config('push-notifications.match_score_enabled', true)) {
            return;
        }

        $beforeHome = (int) ($before['home_score_full_time'] ?? 0);
        $beforeAway = (int) ($before['away_score_full_time'] ?? 0);
        $afterHome = (int) ($after['home_score_full_time'] ?? 0);
        $afterAway = (int) ($after['away_score_full_time'] ?? 0);
        $statusChanged = (string) ($before['status'] ?? '') !== (string) ($after['status'] ?? '');
        $scoreChanged = $beforeHome !== $afterHome || $beforeAway !== $afterAway;

        if (! $scoreChanged && ! $statusChanged) {
            return;
        }

        $poolIds = Prediction::query()
            ->where('football_match_id', (int) $match->id)
            ->distinct()
            ->pluck('pool_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($poolIds === []) {
            return;
        }

        $userIds = PoolMember::query()
            ->whereIn('pool_id', $poolIds)
            ->where('status', 'active')
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($userIds === []) {
            return;
        }

        $home = (string) ($match->homeTeam?->short_name ?: $match->homeTeam?->name ?: 'Casa');
        $away = (string) ($match->awayTeam?->short_name ?: $match->awayTeam?->name ?: 'Visitante');
        $body = "{$home} {$afterHome} x {$afterAway} {$away}";
        if ((string) $match->status === 'FINISHED') {
            $body .= ' (Final)';
        }

        SendWebPushToUsersJob::dispatch(
            userIds: $userIds,
            payload: [
                'title' => $scoreChanged ? 'Atualizacao de placar' : 'Atualizacao de partida',
                'body' => $body,
                'url' => "/pwa/matches/{$match->id}",
                'tag' => "bolao-gol-{$match->id}",
                'renotify' => true,
            ],
            dedupeKey: "match_score:{$match->id}:{$afterHome}:{$afterAway}:{$match->status}",
            dedupeSeconds: 30,
        )->onQueue((string) config('queue.connections.redis.queue', 'default'));
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
        $backfillDays = max(1, (int) config('football-data.match_details.backfill_finished_days', 120));
        $safeLimit = max(1, $limit);

        $baseQuery = FootballMatch::query()
            ->when($competitionCode, fn ($q) => $q->whereHas('competition', fn ($qc) => $qc->where('code', strtoupper((string) $competitionCode))))
            ->when($seasonYear, fn ($q) => $q->whereHas('season', fn ($qs) => $qs->where('year', $seasonYear)))
            ->when($stage !== null && $stage !== '', fn ($q) => $q->where('stage', $stage));

        // Durante jogo ao vivo, garantimos cobertura de lineup/eventos para TODOS os jogos live.
        $liveStatuses = ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'];
        $livePriority = (clone $baseQuery)
            ->whereIn('status', $liveStatuses)
            ->orderByRaw("case football_matches.status when 'IN_PLAY' then 0 when 'PAUSED' then 1 when 'EXTRA_TIME' then 2 when 'PENALTY_SHOOTOUT' then 3 else 4 end")
            ->orderBy('football_matches.utc_date')
            ->select('football_matches.*')
            ->limit($safeLimit)
            ->get();

        if ($livePriority->isNotEmpty()) {
            return $livePriority->values();
        }

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
            ->whereIn('status', ['TIMED', 'SCHEDULED', 'IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT', 'FINISHED'])
            ->orderByRaw("case football_matches.status when 'IN_PLAY' then 0 when 'PAUSED' then 1 when 'EXTRA_TIME' then 2 when 'PENALTY_SHOOTOUT' then 3 when 'TIMED' then 4 when 'SCHEDULED' then 5 else 6 end")
            ->orderBy('football_matches.utc_date')
            ->select('football_matches.*')
            ->limit($remaining)
            ->get();

        return $backfill->concat($priority)
            ->unique(fn (FootballMatch $match) => (int) $match->id)
            ->values();
    }
}
