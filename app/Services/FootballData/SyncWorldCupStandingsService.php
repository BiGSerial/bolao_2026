<?php

namespace App\Services\FootballData;

use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Standing;
use App\Models\Team;
use App\Models\TeamProviderRef;
use Illuminate\Support\Facades\DB;

class SyncWorldCupStandingsService
{
    public function __construct(private readonly TeamCrestCacheService $crestCache)
    {
    }

    public function sync(array $payload, ?int $seasonYear = null, ?string $stageFilter = null): int
    {
        return DB::transaction(function () use ($payload, $seasonYear, $stageFilter): int {
            $competition = Competition::updateOrCreate(
                ['provider' => 'football_data', 'external_id' => data_get($payload, 'competition.id', 0)],
                [
                    'code' => data_get($payload, 'competition.code'),
                    'name' => data_get($payload, 'competition.name', 'World Cup'),
                    'type' => data_get($payload, 'competition.type'),
                    'emblem' => data_get($payload, 'competition.emblem'),
                ]
            );

            $resolvedYear = $seasonYear
                ?: (int) data_get($payload, 'season.year', config('football-data.competitions.WC.season', config('football-data.world_cup.season')));

            $season = CompetitionSeason::updateOrCreate(
                ['provider' => 'football_data', 'external_id' => data_get($payload, 'season.id', 0)],
                [
                    'competition_id' => $competition->id,
                    'year' => $resolvedYear,
                    'start_date' => data_get($payload, 'season.startDate'),
                    'end_date' => data_get($payload, 'season.endDate'),
                    'current_matchday' => data_get($payload, 'season.currentMatchday'),
                    'winner_payload' => data_get($payload, 'season.winner'),
                ]
            );

            $stage = $stageFilter ?: (string) config('football-data.competitions.WC.default_stage', config('football-data.world_cup.stage', 'GROUP_STAGE'));

            Standing::query()
                ->where('provider', 'football_data')
                ->where('competition_id', $competition->id)
                ->where('competition_season_id', $season->id)
                ->when($stage !== '', fn ($q) => $q->where('stage', $stage))
                ->delete();

            $created = 0;

            foreach ((array) data_get($payload, 'standings', []) as $standingPayload) {
                if ($stage !== '' && (string) data_get($standingPayload, 'stage') !== $stage) {
                    continue;
                }

                $standing = Standing::create([
                    'competition_id' => $competition->id,
                    'competition_season_id' => $season->id,
                    'provider' => 'football_data',
                    'external_id' => data_get($standingPayload, 'id'),
                    'stage' => data_get($standingPayload, 'stage'),
                    'type' => data_get($standingPayload, 'type'),
                    'group_name' => data_get($standingPayload, 'group'),
                    'raw_payload' => $standingPayload,
                ]);

                foreach ((array) data_get($standingPayload, 'table', []) as $rowPayload) {
                    $teamExternalId = (int) data_get($rowPayload, 'team.id', 0);
                    $team = null;

                    if ($teamExternalId > 0) {
                        $team = $this->resolveTeamByProviderRef(
                            'football_data',
                            $teamExternalId,
                            [
                                'provider' => 'football_data',
                                'external_id' => $teamExternalId,
                                'name' => data_get($rowPayload, 'team.name', 'TBD'),
                                'short_name' => data_get($rowPayload, 'team.shortName') ?? data_get($rowPayload, 'team.name'),
                                'tla' => data_get($rowPayload, 'team.tla'),
                                'crest' => $this->crestCache->cache(
                                    data_get($rowPayload, 'team.crest') ?? data_get($rowPayload, 'team.crestURI'),
                                    $teamExternalId
                                ),
                            ]
                        );
                    }

                    $standing->rows()->create([
                        'team_id' => $team?->id,
                        'position' => data_get($rowPayload, 'position'),
                        'played_games' => data_get($rowPayload, 'playedGames', 0),
                        'won' => data_get($rowPayload, 'won', 0),
                        'draw' => data_get($rowPayload, 'draw', 0),
                        'lost' => data_get($rowPayload, 'lost', 0),
                        'goal_difference' => data_get($rowPayload, 'goalDifference', 0),
                        'goals_for' => data_get($rowPayload, 'goalsFor', 0),
                        'goals_against' => data_get($rowPayload, 'goalsAgainst', 0),
                        'points' => data_get($rowPayload, 'points', 0),
                        'raw_payload' => $rowPayload,
                    ]);
                }

                $created++;
            }

            return $created;
        });
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
}
