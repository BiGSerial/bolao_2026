<?php

namespace Tests\Feature\Api\V1;

use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MyPredictionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_read_and_write_own_prediction(): void
    {
        [$user, $pool, $match] = $this->baseScenario();
        $token = $user->createToken('test-device')->plainTextToken;

        $readResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/pools/{$pool->id}/matches/{$match->id}/predictions/me");

        $readResponse
            ->assertOk()
            ->assertJsonPath('data.prediction', null)
            ->assertJsonPath('data.lock.is_locked', false);

        $writeResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/pools/{$pool->id}/matches/{$match->id}/predictions/me", [
                'home_score' => 2,
                'away_score' => 1,
            ]);

        $writeResponse
            ->assertOk()
            ->assertJsonPath('data.prediction.home_score', 2)
            ->assertJsonPath('data.prediction.away_score', 1);
    }

    public function test_write_prediction_returns_conflict_when_locked(): void
    {
        [$user, $pool, $match] = $this->baseScenario(now()->utc()->subMinutes(5));
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/pools/{$pool->id}/matches/{$match->id}/predictions/me", [
                'home_score' => 1,
                'away_score' => 1,
            ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PREDICTION_RULE_VIOLATION');
    }

    public function test_user_can_list_predictions_by_pool(): void
    {
        [$user, $pool, $match] = $this->baseScenario();
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/pools/{$pool->id}/matches/{$match->id}/predictions/me", [
                'home_score' => 3,
                'away_score' => 2,
            ])
            ->assertOk();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/pools/{$pool->id}/predictions/me?status=timed&per_page=10");

        $response
            ->assertOk()
            ->assertJsonPath('data.pool_id', $pool->id)
            ->assertJsonPath('meta.pagination.page', 1)
            ->assertJsonPath('meta.pagination.per_page', 10)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.items.0.match.id', $match->id)
            ->assertJsonPath('data.items.0.prediction.home_score', 3)
            ->assertJsonPath('data.items.0.prediction.away_score', 2);
    }

    public function test_list_predictions_by_pool_forbidden_for_non_member(): void
    {
        [$owner, $pool] = $this->baseScenario();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('other-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/pools/{$pool->id}/predictions/me");

        $response
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'POOL_FORBIDDEN');
    }

    private function baseScenario(?\Carbon\Carbon $matchDate = null): array
    {
        $user = User::factory()->create();

        $competition = Competition::query()->create([
            'provider' => 'football_data',
            'external_id' => random_int(1000, 9999),
            'code' => 'WC',
            'name' => 'World Cup',
        ]);

        $season = CompetitionSeason::query()->create([
            'competition_id' => $competition->id,
            'provider' => 'football_data',
            'external_id' => random_int(10000, 19999),
            'year' => 2026,
        ]);

        $teamA = Team::query()->create([
            'provider' => 'football_data',
            'external_id' => random_int(20000, 29999),
            'name' => 'Brazil',
            'short_name' => 'Brazil',
            'tla' => 'BRA',
        ]);

        $teamB = Team::query()->create([
            'provider' => 'football_data',
            'external_id' => random_int(30000, 39999),
            'name' => 'France',
            'short_name' => 'France',
            'tla' => 'FRA',
        ]);

        $pool = Pool::query()->create([
            'owner_id' => $user->id,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'name' => 'Pool Pred',
            'slug' => 'pool-pred',
            'visibility' => 'invite_only',
            'status' => 'active',
            'stage' => 'GROUP_STAGE',
            'prediction_lock_minutes' => 30,
            'invite_code' => Str::upper(Str::random(10)),
        ]);

        PoolMember::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $match = FootballMatch::query()->create([
            'provider' => 'football_data',
            'external_id' => random_int(40000, 49999),
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'utc_date' => $matchDate ?? now()->utc()->addHours(2),
            'status' => 'TIMED',
            'stage' => 'GROUP_STAGE',
        ]);

        return [$user, $pool, $match];
    }
}
