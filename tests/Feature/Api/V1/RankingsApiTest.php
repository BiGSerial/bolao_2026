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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RankingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_fetch_rankings_and_live_rankings(): void
    {
        [$viewer, $pool] = $this->seedRankingScenario();
        $token = $viewer->createToken('test-device')->plainTextToken;

        $ranking = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/pools/{$pool->id}/rankings");

        $ranking
            ->assertOk()
            ->assertJsonPath('data.pool_id', $pool->id)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.position', 1)
            ->assertJsonPath('data.items.0.points_total', 5);

        $live = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/pools/{$pool->id}/rankings/live");

        $live
            ->assertOk()
            ->assertJsonPath('data.pool_id', $pool->id)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.position', 1)
            ->assertJsonPath('data.items.0.points_total', 5);
    }

    public function test_non_member_cannot_fetch_rankings(): void
    {
        [$viewer, $pool] = $this->seedRankingScenario();
        $other = User::factory()->create();
        $token = $other->createToken('other-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/pools/{$pool->id}/rankings");

        $response->assertStatus(403)->assertJsonPath('error.code', 'POOL_FORBIDDEN');
    }

    private function seedRankingScenario(): array
    {
        $owner = User::factory()->create(['name' => 'Owner User', 'display_name' => 'Owner']);
        $member = User::factory()->create(['name' => 'Member User', 'display_name' => 'Member']);

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

        $home = Team::query()->create([
            'provider' => 'football_data',
            'external_id' => random_int(20000, 29999),
            'name' => 'Brazil',
            'short_name' => 'Brazil',
            'tla' => 'BRA',
        ]);

        $away = Team::query()->create([
            'provider' => 'football_data',
            'external_id' => random_int(30000, 39999),
            'name' => 'France',
            'short_name' => 'France',
            'tla' => 'FRA',
        ]);

        $pool = Pool::query()->create([
            'owner_id' => $owner->id,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'name' => 'Pool Ranking',
            'slug' => 'pool-ranking',
            'visibility' => 'invite_only',
            'status' => 'active',
            'stage' => 'GROUP_STAGE',
            'points_exact_score' => 5,
            'points_correct_result' => 3,
            'points_correct_goals' => 1,
            'invite_code' => Str::upper(Str::random(10)),
        ]);

        PoolMember::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        PoolMember::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $match = FootballMatch::query()->create([
            'provider' => 'football_data',
            'external_id' => random_int(40000, 49999),
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'utc_date' => now()->utc()->subHours(2),
            'status' => 'FINISHED',
            'stage' => 'GROUP_STAGE',
            'home_score_full_time' => 2,
            'away_score_full_time' => 1,
        ]);

        Prediction::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $owner->id,
            'football_match_id' => $match->id,
            'home_score' => 2,
            'away_score' => 1,
            'points' => 5,
            'eligible' => true,
        ]);

        Prediction::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $member->id,
            'football_match_id' => $match->id,
            'home_score' => 1,
            'away_score' => 1,
            'points' => 0,
            'eligible' => true,
        ]);

        return [$owner, $pool];
    }
}
