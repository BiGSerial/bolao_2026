<?php

namespace App\Services\FootballData;

use App\Events\MatchUpdated;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FootballMatch;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncWorldCupMatchesService
{
    public function __construct(private readonly TeamCrestCacheService $crestCache)
    {
    }

    public function sync(array $payload): Collection
    {
        return DB::transaction(function () use ($payload): Collection {
            $competition = Competition::updateOrCreate(
                ['provider' => 'football_data', 'external_id' => $payload['competition']['id']],
                ['code' => $payload['competition']['code'] ?? null, 'name' => $payload['competition']['name'], 'type' => $payload['competition']['type'] ?? null, 'emblem' => $payload['competition']['emblem'] ?? null]
            );

            $season = CompetitionSeason::updateOrCreate(
                ['provider' => 'football_data', 'external_id' => $payload['matches'][0]['season']['id'] ?? 0],
                ['competition_id' => $competition->id, 'year' => config('football-data.world_cup.season'), 'start_date' => data_get($payload, 'matches.0.season.startDate'), 'end_date' => data_get($payload, 'matches.0.season.endDate'), 'current_matchday' => data_get($payload, 'matches.0.season.currentMatchday'), 'winner_payload' => data_get($payload, 'matches.0.season.winner')]
            );

            $changed = collect();

            foreach ($payload['matches'] as $matchPayload) {
                $home = Team::updateOrCreate(
                    ['provider' => 'football_data', 'external_id' => $matchPayload['homeTeam']['id']],
                    [
                        'name' => $matchPayload['homeTeam']['name'] ?? 'TBD',
                        'short_name' => $matchPayload['homeTeam']['shortName'] ?? null,
                        'tla' => $matchPayload['homeTeam']['tla'] ?? null,
                        'crest' => $this->crestCache->cache($matchPayload['homeTeam']['crest'] ?? null, $matchPayload['homeTeam']['id'] ?? null),
                    ]
                );

                $away = Team::updateOrCreate(
                    ['provider' => 'football_data', 'external_id' => $matchPayload['awayTeam']['id']],
                    [
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

                $match = FootballMatch::updateOrCreate(
                    ['provider' => 'football_data', 'external_id' => $matchPayload['id']],
                    [
                        'competition_id' => $competition->id,
                        'competition_season_id' => $season->id,
                        'home_team_id' => $home->id,
                        'away_team_id' => $away->id,
                        'utc_date' => $utcDate,
                        'local_date' => $utcDate->copy()->timezone('America/Sao_Paulo'),
                        'status' => $matchPayload['status'],
                        'matchday' => $matchPayload['matchday'] ?? null,
                        'stage' => $matchPayload['stage'] ?? null,
                        'group_name' => $matchPayload['group'] ?? null,
                        'score_winner' => $score['winner'] ?? null,
                        'score_duration' => $score['duration'] ?? null,
                        'home_score_full_time' => data_get($score, 'fullTime.home'),
                        'away_score_full_time' => data_get($score, 'fullTime.away'),
                        'home_score_half_time' => data_get($score, 'halfTime.home'),
                        'away_score_half_time' => data_get($score, 'halfTime.away'),
                        'home_score_extra_time' => data_get($score, 'extraTime.home'),
                        'away_score_extra_time' => data_get($score, 'extraTime.away'),
                        'home_score_penalties' => data_get($score, 'penalties.home'),
                        'away_score_penalties' => data_get($score, 'penalties.away'),
                        'last_updated_by_provider_at' => isset($matchPayload['lastUpdated']) ? Carbon::parse($matchPayload['lastUpdated']) : null,
                        'raw_payload' => $matchPayload,
                    ]
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
}
