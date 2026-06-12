<?php

namespace Tests\Feature\Api\V1;

use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FootballMatch;
use App\Models\Standing;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cup_standings_use_total_table_and_sort_each_group_by_fifa_criteria(): void
    {
        $user = User::factory()->create();
        $competition = Competition::query()->create([
            'provider' => 'football_data',
            'external_id' => 2000,
            'code' => 'WC',
            'name' => 'World Cup',
            'type' => 'CUP',
        ]);
        $season = CompetitionSeason::query()->create([
            'competition_id' => $competition->id,
            'provider' => 'football_data',
            'external_id' => 2026,
            'year' => 2026,
        ]);

        $groupA = Standing::query()->create([
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'provider' => 'football_data',
            'stage' => 'GROUP_STAGE',
            'type' => 'TOTAL',
            'group_name' => 'GROUP_A',
        ]);
        $homeTable = Standing::query()->create([
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'provider' => 'football_data',
            'stage' => 'GROUP_STAGE',
            'type' => 'HOME',
            'group_name' => 'GROUP_A',
        ]);

        $teams = collect([
            ['name' => 'Brasil', 'tla' => 'BRA', 'points' => 4, 'goal_difference' => 1, 'goals_for' => 3, 'position' => 1],
            ['name' => 'França', 'tla' => 'FRA', 'points' => 6, 'goal_difference' => 2, 'goals_for' => 4, 'position' => 3],
            ['name' => 'Japão', 'tla' => 'JPN', 'points' => 4, 'goal_difference' => 2, 'goals_for' => 2, 'position' => 2],
            ['name' => 'Canadá', 'tla' => 'CAN', 'points' => 1, 'goal_difference' => -3, 'goals_for' => 1, 'position' => 4],
        ])->map(function (array $data, int $index) use ($groupA): Team {
            $team = Team::query()->create([
                'provider' => 'football_data',
                'external_id' => 3000 + $index,
                'name' => $data['name'],
                'short_name' => $data['name'],
                'tla' => $data['tla'],
            ]);
            $groupA->rows()->create([
                'team_id' => $team->id,
                'position' => $data['position'],
                'played_games' => 3,
                'won' => 1,
                'draw' => 1,
                'lost' => 1,
                'points' => $data['points'],
                'goals_for' => $data['goals_for'],
                'goals_against' => 2,
                'goal_difference' => $data['goal_difference'],
            ]);

            return $team;
        });
        $homeTable->rows()->create([
            'team_id' => $teams->first()->id,
            'position' => 1,
            'played_games' => 1,
            'points' => 3,
        ]);

        $token = $user->createToken('test-device')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/standings?competition_id={$competition->id}");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.groups')
            ->assertJsonPath('data.groups.0.name', 'Grupo A')
            ->assertJsonPath('data.groups.0.rows.0.team.tla', 'FRA')
            ->assertJsonPath('data.groups.0.rows.1.team.tla', 'JPN')
            ->assertJsonPath('data.groups.0.rows.2.team.tla', 'BRA')
            ->assertJsonPath('data.groups.0.rows.0.position', 1)
            ->assertJsonPath('data.groups.0.rows.0.played_games', 3)
            ->assertJsonPath('data.groups.0.rows.0.goals_for', 4)
            ->assertJsonPath('data.groups.0.rows.0.goals_against', 2)
            ->assertJsonPath('data.groups.0.rows.0.goal_difference', 2)
            ->assertJsonPath('data.groups.0.rows.0.points', 6);
    }

    public function test_general_cup_table_is_grouped_by_match_group_instead_of_row_chunks(): void
    {
        $user = User::factory()->create();
        $competition = Competition::query()->create([
            'provider' => 'football_data',
            'external_id' => 2100,
            'code' => 'WC',
            'name' => 'World Cup',
            'type' => 'CUP',
        ]);
        $season = CompetitionSeason::query()->create([
            'competition_id' => $competition->id,
            'provider' => 'football_data',
            'external_id' => 2126,
            'year' => 2026,
        ]);
        $standing = Standing::query()->create([
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'provider' => 'football_data',
            'stage' => 'GROUP_STAGE',
            'type' => 'TOTAL',
            'group_name' => null,
        ]);

        $teams = collect(range(1, 8))->map(function (int $number) use ($standing): Team {
            $team = Team::query()->create([
                'provider' => 'football_data',
                'external_id' => 4000 + $number,
                'name' => "Team {$number}",
                'short_name' => "Team {$number}",
                'tla' => "T{$number}",
            ]);
            $standing->rows()->create([
                'team_id' => $team->id,
                'position' => $number,
                'played_games' => 3,
                'points' => 9 - $number,
                'goals_for' => 9 - $number,
                'goals_against' => $number,
                'goal_difference' => 9 - ($number * 2),
            ]);

            return $team;
        });

        foreach ([
            ['GROUP_A', 0, 2],
            ['GROUP_A', 4, 6],
            ['GROUP_B', 1, 3],
            ['GROUP_B', 5, 7],
        ] as $index => [$groupName, $homeIndex, $awayIndex]) {
            FootballMatch::query()->create([
                'provider' => 'football_data',
                'external_id' => 5000 + $index,
                'competition_id' => $competition->id,
                'competition_season_id' => $season->id,
                'home_team_id' => $teams[$homeIndex]->id,
                'away_team_id' => $teams[$awayIndex]->id,
                'utc_date' => now()->utc()->addDays($index + 1),
                'status' => 'TIMED',
                'stage' => 'GROUP_STAGE',
                'group_name' => $groupName,
            ]);
        }

        $token = $user->createToken('test-device')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/standings?competition_id={$competition->id}");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data.groups')
            ->assertJsonPath('data.groups.0.name', 'Grupo A')
            ->assertJsonPath('data.groups.1.name', 'Grupo B')
            ->assertJsonCount(4, 'data.groups.0.rows')
            ->assertJsonCount(4, 'data.groups.1.rows')
            ->assertJsonPath('data.groups.0.rows.0.team.tla', 'T1')
            ->assertJsonPath('data.groups.0.rows.1.team.tla', 'T3')
            ->assertJsonPath('data.groups.1.rows.0.team.tla', 'T2')
            ->assertJsonPath('data.groups.1.rows.1.team.tla', 'T4');
    }
}
