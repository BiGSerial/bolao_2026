<?php

namespace Tests\Feature\Api\V1;

use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\Prediction;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
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

    public function test_closed_predictions_lock_time_is_returned_in_local_timezone(): void
    {
        [$user, $pool, $match] = $this->baseScenario(now()->utc()->addMinutes(20));
        $pool->update(['closed_predictions' => true]);
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/pools/{$pool->id}/matches/{$match->id}/predictions/me");

        $response
            ->assertOk()
            ->assertJsonPath('data.lock.is_locked', true);

        $this->assertStringEndsWith('-03:00', (string) $response->json('data.lock.lock_at'));
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

    public function test_user_can_list_saved_prediction_after_match_is_finished(): void
    {
        [$user, $pool, $match] = $this->baseScenario(now()->utc()->subHours(2));
        $match->update([
            'status' => 'FINISHED',
            'home_score_full_time' => 2,
            'away_score_full_time' => 1,
        ]);
        Prediction::query()->create([
            'pool_id' => $pool->id,
            'football_match_id' => $match->id,
            'user_id' => $user->id,
            'home_score' => 1,
            'away_score' => 0,
            'last_changed_at' => now(),
        ]);
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/pools/{$pool->id}/predictions/me?per_page=10");

        $response
            ->assertOk()
            ->assertJsonPath('data.items.0.match.status', 'FINISHED')
            ->assertJsonPath('data.items.0.match.score.home', 2)
            ->assertJsonPath('data.items.0.prediction.home_score', 1)
            ->assertJsonPath('data.items.0.lock.is_locked', true);
    }

    public function test_user_can_view_other_member_predictions_only_for_locked_or_finished_matches(): void
    {
        [$viewer, $pool, $openMatch] = $this->baseScenario(now()->utc()->addHours(2));
        $target = User::factory()->create(['name' => 'Target User', 'display_name' => 'Target']);
        PoolMember::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $target->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $lockedMatch = $this->createMatchLike($openMatch, now()->utc()->addMinutes(10), 'TIMED', 50001);
        $finishedMatch = $this->createMatchLike($openMatch, now()->utc()->subHours(2), 'FINISHED', 50002, 2, 1);

        foreach ([$openMatch, $lockedMatch, $finishedMatch] as $index => $match) {
            Prediction::query()->create([
                'pool_id' => $pool->id,
                'football_match_id' => $match->id,
                'user_id' => $target->id,
                'home_score' => $index,
                'away_score' => 0,
                'last_changed_at' => now(),
            ]);
        }

        $token = $viewer->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/pools/{$pool->id}/predictions/users/{$target->id}?per_page=10");

        $response
            ->assertOk()
            ->assertJsonPath('data.visibility.all_matches', false)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.match.id', $finishedMatch->id)
            ->assertJsonPath('data.items.0.match.score.home', 2)
            ->assertJsonPath('data.items.0.prediction.points', 4)
            ->assertJsonPath('data.items.1.match.id', $lockedMatch->id);
    }

    public function test_closed_predictions_locked_pool_exposes_future_predictions_from_other_member(): void
    {
        [$viewer, $pool, $pastMatch] = $this->baseScenario(now()->utc()->subHours(2));
        $pool->update(['closed_predictions' => true]);
        $target = User::factory()->create(['name' => 'Target User', 'display_name' => 'Target']);
        PoolMember::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $target->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $futureMatch = $this->createMatchLike($pastMatch, now()->utc()->addHours(2), 'TIMED', 50003);

        foreach ([$pastMatch, $futureMatch] as $index => $match) {
            Prediction::query()->create([
                'pool_id' => $pool->id,
                'football_match_id' => $match->id,
                'user_id' => $target->id,
                'home_score' => $index + 1,
                'away_score' => 0,
                'last_changed_at' => now(),
            ]);
        }

        $token = $viewer->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/pools/{$pool->id}/predictions/users/{$target->id}?per_page=10");

        $response
            ->assertOk()
            ->assertJsonPath('data.visibility.all_matches', true)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.1.match.id', $futureMatch->id)
            ->assertJsonPath('data.items.1.prediction.home_score', 2)
            ->assertJsonPath('data.items.1.prediction.points', null);
    }

    private function baseScenario(?Carbon $matchDate = null): array
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

    private function createMatchLike(
        FootballMatch $base,
        Carbon $matchDate,
        string $status,
        int $externalId,
        ?int $homeScore = null,
        ?int $awayScore = null,
    ): FootballMatch {
        return FootballMatch::query()->create([
            'provider' => 'football_data',
            'external_id' => $externalId,
            'competition_id' => $base->competition_id,
            'competition_season_id' => $base->competition_season_id,
            'home_team_id' => $base->home_team_id,
            'away_team_id' => $base->away_team_id,
            'utc_date' => $matchDate,
            'status' => $status,
            'stage' => $base->stage,
            'home_score_full_time' => $homeScore,
            'away_score_full_time' => $awayScore,
        ]);
    }
}
