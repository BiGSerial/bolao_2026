<?php

namespace Tests\Feature\Api\V1;

use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_matches_supports_filters_and_pagination(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        [$competition, $season, $teamA, $teamB] = $this->baseEntities();

        FootballMatch::query()->create([
            'provider' => 'football_data',
            'external_id' => 9001,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'utc_date' => now()->utc()->addDay(),
            'status' => 'TIMED',
        ]);

        FootballMatch::query()->create([
            'provider' => 'football_data',
            'external_id' => 9002,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $teamB->id,
            'away_team_id' => $teamA->id,
            'utc_date' => now()->utc()->addDays(2),
            'status' => 'FINISHED',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/matches?status=timed&per_page=5');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.status', 'TIMED')
            ->assertJsonPath('meta.pagination.page', 1)
            ->assertJsonPath('meta.pagination.per_page', 5)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonStructure([
                'data' => ['items'],
                'meta' => ['request_id', 'version', 'pagination'],
            ]);
    }

    public function test_show_returns_match_details(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        [$competition, $season, $teamA, $teamB] = $this->baseEntities();

        $match = FootballMatch::query()->create([
            'provider' => 'football_data',
            'external_id' => 9010,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'utc_date' => now()->utc()->addHours(6),
            'status' => 'SCHEDULED',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/matches/'.$match->id);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $match->id)
            ->assertJsonPath('data.status', 'SCHEDULED')
            ->assertJsonPath('data.competition.id', $competition->id)
            ->assertJsonPath('data.home_team.id', $teamA->id)
            ->assertJsonPath('data.away_team.id', $teamB->id);
    }

    private function baseEntities(): array
    {
        $competition = Competition::query()->create([
            'provider' => 'football_data',
            'external_id' => random_int(2000, 9000),
            'code' => 'WC',
            'name' => 'World Cup',
        ]);

        $season = CompetitionSeason::query()->create([
            'competition_id' => $competition->id,
            'provider' => 'football_data',
            'external_id' => random_int(3000, 9000),
            'year' => 2026,
        ]);

        $teamA = Team::query()->create([
            'provider' => 'football_data',
            'external_id' => random_int(10000, 20000),
            'name' => 'Brazil',
            'short_name' => 'Brazil',
            'tla' => 'BRA',
        ]);

        $teamB = Team::query()->create([
            'provider' => 'football_data',
            'external_id' => random_int(20001, 30000),
            'name' => 'France',
            'short_name' => 'France',
            'tla' => 'FRA',
        ]);

        return [$competition, $season, $teamA, $teamB];
    }
}
