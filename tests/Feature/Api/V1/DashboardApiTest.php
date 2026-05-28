<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PoolMemberStatus;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\Prediction;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_dashboard_payload_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $competition = Competition::query()->create([
            'provider' => 'football_data',
            'external_id' => 101,
            'code' => 'WC',
            'name' => 'World Cup',
        ]);

        $season = CompetitionSeason::query()->create([
            'competition_id' => $competition->id,
            'provider' => 'football_data',
            'external_id' => 2026,
            'year' => 2026,
        ]);

        $teamA = Team::query()->create([
            'provider' => 'football_data',
            'external_id' => 1,
            'name' => 'Brazil',
            'short_name' => 'Brazil',
            'tla' => 'BRA',
        ]);

        $teamB = Team::query()->create([
            'provider' => 'football_data',
            'external_id' => 2,
            'name' => 'France',
            'short_name' => 'France',
            'tla' => 'FRA',
        ]);

        FootballMatch::query()->create([
            'provider' => 'football_data',
            'external_id' => 501,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'utc_date' => now()->utc()->subMinutes(10),
            'status' => 'IN_PLAY',
        ]);

        $upcomingMatch = FootballMatch::query()->create([
            'provider' => 'football_data',
            'external_id' => 502,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $teamB->id,
            'away_team_id' => $teamA->id,
            'utc_date' => now()->utc()->addHours(3),
            'status' => 'TIMED',
        ]);

        $pool = Pool::query()->create([
            'owner_id' => $user->id,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'name' => 'Pool A',
            'slug' => 'pool-a',
            'invite_code' => Str::upper(Str::random(10)),
            'status' => 'active',
            'prediction_lock_minutes' => 30,
        ]);

        PoolMember::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => PoolMemberStatus::Active->value,
        ]);

        // Keep one pending prediction by creating another predicted match in same pool.
        Prediction::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'football_match_id' => $upcomingMatch->id,
            'home_score' => 1,
            'away_score' => 0,
        ]);

        $notPredicted = FootballMatch::query()->create([
            'provider' => 'football_data',
            'external_id' => 503,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'utc_date' => now()->utc()->addHours(5),
            'status' => 'SCHEDULED',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.live_matches_count', 1)
            ->assertJsonPath('data.summary.pools_count', 1)
            ->assertJsonPath('data.summary.pending_predictions_count', 1)
            ->assertJsonFragment(['id' => $notPredicted->id])
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'live_matches_count',
                        'upcoming_matches_count',
                        'pools_count',
                        'pending_predictions_count',
                    ],
                    'live_matches',
                    'upcoming_matches',
                    'pools',
                ],
                'meta' => ['request_id', 'version'],
            ]);
    }
}
