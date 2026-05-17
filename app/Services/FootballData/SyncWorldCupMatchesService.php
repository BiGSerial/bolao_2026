<?php

namespace App\Services\FootballData;

use App\Events\MatchUpdated;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\TeamProviderRef;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SyncWorldCupMatchesService
{
    public function __construct(private readonly TeamCrestCacheService $crestCache)
    {
    }

    public function sync(array $payload, ?int $seasonYear = null): Collection
    {
        return DB::transaction(function () use ($payload, $seasonYear): Collection {
            $competition = Competition::updateOrCreate(
                ['provider' => 'football_data', 'external_id' => $payload['competition']['id']],
                [
                    'code' => $payload['competition']['code'] ?? null,
                    'name' => $payload['competition']['name'],
                    'type' => $payload['competition']['type'] ?? null,
                    'emblem' => $payload['competition']['emblem'] ?? null,
                ]
            );

            $resolvedYear = $seasonYear
                ?: (int) data_get($payload, 'matches.0.season.year', config('football-data.competitions.WC.season', config('football-data.world_cup.season')));

            $season = CompetitionSeason::updateOrCreate(
                ['provider' => 'football_data', 'external_id' => $payload['matches'][0]['season']['id'] ?? 0],
                [
                    'competition_id' => $competition->id,
                    'year' => $resolvedYear,
                    'start_date' => data_get($payload, 'matches.0.season.startDate'),
                    'end_date' => data_get($payload, 'matches.0.season.endDate'),
                    'current_matchday' => data_get($payload, 'matches.0.season.currentMatchday'),
                    'winner_payload' => data_get($payload, 'matches.0.season.winner'),
                ]
            );

            $changed = collect();

            foreach ($payload['matches'] as $matchPayload) {
                $home = $this->resolveTeamByProviderRef(
                    'football_data',
                    (int) ($matchPayload['homeTeam']['id'] ?? 0),
                    [
                        'provider' => 'football_data',
                        'external_id' => (int) ($matchPayload['homeTeam']['id'] ?? 0),
                        'name' => $matchPayload['homeTeam']['name'] ?? 'TBD',
                        'short_name' => $matchPayload['homeTeam']['shortName'] ?? null,
                        'tla' => $matchPayload['homeTeam']['tla'] ?? null,
                        'crest' => $this->crestCache->cache($matchPayload['homeTeam']['crest'] ?? null, $matchPayload['homeTeam']['id'] ?? null),
                    ]
                );

                $away = $this->resolveTeamByProviderRef(
                    'football_data',
                    (int) ($matchPayload['awayTeam']['id'] ?? 0),
                    [
                        'provider' => 'football_data',
                        'external_id' => (int) ($matchPayload['awayTeam']['id'] ?? 0),
                        'name' => $matchPayload['awayTeam']['name'] ?? 'TBD',
                        'short_name' => $matchPayload['awayTeam']['shortName'] ?? null,
                        'tla' => $matchPayload['awayTeam']['tla'] ?? null,
                        'crest' => $this->crestCache->cache($matchPayload['awayTeam']['crest'] ?? null, $matchPayload['awayTeam']['id'] ?? null),
                    ]
                );

                $existing = FootballMatch::query()->where('provider', 'football_data')->where('external_id', $matchPayload['id'])->first();
                $before = $existing ? [$existing->status, $existing->home_score_full_time, $existing->away_score_full_time] : null;

                $score = $matchPayload['score'] ?? [];
                $utcDate = Carbon::parse($matchPayload['utcDate'])->utc();
                $incomingStatus = (string) ($matchPayload['status'] ?? '');
                $resolvedStatus = $this->resolveIncomingStatus($existing, $incomingStatus);
                $attributes = [
                    'competition_id' => $competition->id,
                    'competition_season_id' => $season->id,
                    'home_team_id' => $home->id,
                    'away_team_id' => $away->id,
                    'utc_date' => $utcDate,
                    'local_date' => $utcDate->copy()->timezone('America/Sao_Paulo'),
                    // Atualiza status do provider base quando necessario (especialmente terminal),
                    // mas preserva status da API paga durante fase ao vivo.
                    'status' => $resolvedStatus,
                    'matchday' => $matchPayload['matchday'] ?? null,
                    'stage' => $matchPayload['stage'] ?? null,
                    'group_name' => $matchPayload['group'] ?? null,
                    'score_winner' => $score['winner'] ?? null,
                    'score_duration' => $score['duration'] ?? null,
                    'home_score_full_time' => $this->coalescePayloadValue($existing, $score, 'fullTime.home', 'home_score_full_time'),
                    'away_score_full_time' => $this->coalescePayloadValue($existing, $score, 'fullTime.away', 'away_score_full_time'),
                    'home_score_half_time' => $this->coalescePayloadValue($existing, $score, 'halfTime.home', 'home_score_half_time'),
                    'away_score_half_time' => $this->coalescePayloadValue($existing, $score, 'halfTime.away', 'away_score_half_time'),
                    'home_score_extra_time' => $this->coalescePayloadValue($existing, $score, 'extraTime.home', 'home_score_extra_time'),
                    'away_score_extra_time' => $this->coalescePayloadValue($existing, $score, 'extraTime.away', 'away_score_extra_time'),
                    'home_score_penalties' => $this->coalescePayloadValue($existing, $score, 'penalties.home', 'home_score_penalties'),
                    'away_score_penalties' => $this->coalescePayloadValue($existing, $score, 'penalties.away', 'away_score_penalties'),
                    'last_updated_by_provider_at' => isset($matchPayload['lastUpdated']) ? Carbon::parse($matchPayload['lastUpdated']) : null,
                    'raw_payload' => $matchPayload,
                ];

                $attributes = $this->applyLiveStatusTracking($existing, $resolvedStatus, $attributes);

                $match = FootballMatch::updateOrCreate(
                    ['provider' => 'football_data', 'external_id' => $matchPayload['id']],
                    $attributes
                );

                $after = [$match->status, $match->home_score_full_time, $match->away_score_full_time];
                if ($before === null || $before !== $after) {
                    $changed->push($match);
                    MatchUpdated::dispatch($match);
                }
            }

            return $changed;
        });
    }

    /**
     * Mantém valor atual se o payload não trouxe o campo (ou veio nulo).
     */
    private function coalescePayloadValue(?FootballMatch $existing, array $payload, string $path, string $field): mixed
    {
        if (! Arr::has($payload, $path)) {
            return $existing?->{$field};
        }

        $incoming = data_get($payload, $path);
        if ($incoming === null) {
            return $existing?->{$field};
        }

        return $incoming;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function resolveTeamByProviderRef(string $provider, int $externalId, array $attributes): Team
    {
        $ref = TeamProviderRef::query()
            ->where('provider', $provider)
            ->where('external_id', $externalId)
            ->first();

        if ($ref) {
            $team = Team::query()->findOrFail($ref->team_id);
            $team->fill($attributes);
            $team->save();

            return $team;
        }

        $team = Team::create($attributes);

        TeamProviderRef::updateOrCreate(
            ['provider' => $provider, 'external_id' => $externalId],
            ['team_id' => $team->id]
        );

        return $team;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function applyLiveStatusTracking(?FootballMatch $existing, string $newStatus, array $attributes): array
    {
        $nowUtc = now()->utc();
        $previousStatus = (string) ($existing?->status ?? '');
        $accumulated = (int) ($existing?->live_clock_accumulated_seconds ?? 0);
        $anchor = $existing?->live_clock_anchor_at;

        if ($existing && $previousStatus === 'IN_PLAY' && $newStatus !== 'IN_PLAY' && $anchor) {
            $accumulated += max(0, $anchor->diffInSeconds($nowUtc));
            $attributes['live_clock_accumulated_seconds'] = $accumulated;
            $attributes['live_clock_anchor_at'] = null;
        } elseif ($existing) {
            $attributes['live_clock_accumulated_seconds'] = $accumulated;
            $attributes['live_clock_anchor_at'] = $anchor;
        } else {
            $attributes['live_clock_accumulated_seconds'] = 0;
        }

        if ($newStatus === 'IN_PLAY' && $previousStatus !== 'IN_PLAY') {
            $attributes['in_play_started_at'] = $existing?->in_play_started_at ?? $nowUtc;
            $attributes['live_clock_anchor_at'] = $nowUtc;

            if ($previousStatus === 'PAUSED') {
                $attributes['resumed_from_interval_at'] = $nowUtc;
            }
        }

        if ($newStatus === 'IN_PLAY' && ($existing?->in_play_started_at === null || $existing?->live_clock_anchor_at === null)) {
            $attributes['in_play_started_at'] = $existing?->in_play_started_at ?? $nowUtc;
            $attributes['live_clock_anchor_at'] = $existing?->live_clock_anchor_at ?? $nowUtc;
        }

        if ($newStatus === 'PAUSED' && $previousStatus !== 'PAUSED') {
            $attributes['interval_started_at'] = $nowUtc;
        }

        if ($newStatus === 'FINISHED' && $previousStatus !== 'FINISHED') {
            $attributes['finished_at'] = $nowUtc;
        }

        return $attributes;
    }

    private function resolveIncomingStatus(?FootballMatch $existing, string $incomingStatus): string
    {
        if (! $existing) {
            return $incomingStatus;
        }

        $currentStatus = (string) ($existing->status ?? '');
        if ($incomingStatus === '') {
            return $currentStatus;
        }

        $isTerminalIncoming = in_array($incomingStatus, ['FINISHED', 'POSTPONED', 'CANCELLED'], true);
        if ($isTerminalIncoming) {
            return $incomingStatus;
        }

        $hasApiFootballLiveStatus = (string) data_get($existing->raw_payload, 'api_football_status.short', '') !== '';
        if ($hasApiFootballLiveStatus) {
            return $currentStatus;
        }

        return $incomingStatus;
    }
}
