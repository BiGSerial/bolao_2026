<?php

namespace Tests\Feature\Domain;

use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\Prediction;
use App\Models\Team;
use App\Models\User;
use App\Services\Pools\PoolRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PoolRankingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranking_counts_only_active_members_eligible_predictions_and_finished_matches_in_stage(): void
    {
        $owner = User::factory()->create();
        $activeUser = User::factory()->create();
        $inactiveUser = User::factory()->create();

        $pool = Pool::create([
            'owner_id' => $owner->id,
            'name' => 'Pool Ranking',
            'slug' => 'pool-ranking-'.uniqid(),
            'visibility' => 'invite_only',
            'status' => 'active',
            'invite_code' => strtoupper(substr(uniqid('GH'), 0, 8)),
            'allow_prediction_changes' => true,
            'prediction_lock_minutes' => 120,
            'allow_pending_member_predictions' => true,
            'stage' => 'GROUP_STAGE',
        ]);

        $pool->members()->create(['user_id' => $activeUser->id, 'role' => 'member', 'status' => 'active']);
        $pool->members()->create(['user_id' => $inactiveUser->id, 'role' => 'member', 'status' => 'inactive']);

        [$matchFinishedInStage, $matchFinishedOutStage, $matchNotFinished] = $this->makeMatches();

        Prediction::create([
            'pool_id' => $pool->id,
            'user_id' => $activeUser->id,
            'football_match_id' => $matchFinishedInStage->id,
            'home_score' => 1,
            'away_score' => 0,
            'points' => 5,
            'eligible' => true,
            'last_changed_at' => now()->subHours(4),
        ]);

        Prediction::create([
            'pool_id' => $pool->id,
            'user_id' => $activeUser->id,
            'football_match_id' => $matchFinishedOutStage->id,
            'home_score' => 1,
            'away_score' => 0,
            'points' => 5,
            'eligible' => true,
            'last_changed_at' => now()->subHours(4),
        ]);

        Prediction::create([
            'pool_id' => $pool->id,
            'user_id' => $activeUser->id,
            'football_match_id' => $matchNotFinished->id,
            'home_score' => 1,
            'away_score' => 0,
            'points' => 5,
            'eligible' => true,
            'last_changed_at' => now()->subHours(4),
        ]);

        Prediction::create([
            'pool_id' => $pool->id,
            'user_id' => $inactiveUser->id,
            'football_match_id' => $matchFinishedInStage->id,
            'home_score' => 1,
            'away_score' => 0,
            'points' => 5,
            'eligible' => true,
            'last_changed_at' => now()->subHours(4),
        ]);

        app(PoolRankingService::class)->recalculate($pool);

        $rows = DB::table('pool_rankings')->where('pool_id', $pool->id)->get();

        $this->assertCount(1, $rows);
        $this->assertSame($activeUser->id, $rows[0]->user_id);
        $this->assertSame(5, (int) $rows[0]->points_total);
        $this->assertSame(1, (int) $rows[0]->predictions_counted);
    }

    public function test_ranking_preserves_previous_rows_when_new_base_is_empty(): void
    {
        $owner = User::factory()->create();
        $activeUser = User::factory()->create();

        $pool = Pool::create([
            'owner_id' => $owner->id,
            'name' => 'Pool Preserve Ranking',
            'slug' => 'pool-preserve-ranking-'.uniqid(),
            'visibility' => 'invite_only',
            'status' => 'active',
            'invite_code' => strtoupper(substr(uniqid('GH'), 0, 8)),
            'allow_prediction_changes' => true,
            'prediction_lock_minutes' => 120,
            'allow_pending_member_predictions' => true,
            'stage' => 'GROUP_STAGE',
        ]);

        DB::table('pool_rankings')->insert([
            'pool_id' => $pool->id,
            'user_id' => $activeUser->id,
            'points_total' => 9,
            'exact_scores' => 1,
            'correct_results' => 2,
            'correct_home_goals' => 2,
            'correct_away_goals' => 1,
            'predictions_counted' => 3,
            'position' => 1,
            'last_calculated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(PoolRankingService::class)->recalculate($pool);

        $rows = DB::table('pool_rankings')->where('pool_id', $pool->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($activeUser->id, $rows[0]->user_id);
        $this->assertSame(9, (int) $rows[0]->points_total);
    }

    private function makeMatches(): array
    {
        $competition = Competition::create([
            'provider' => 'football_data',
            'external_id' => 2000,
            'code' => 'WC',
            'name' => 'FIFA World Cup',
            'type' => 'CUP',
        ]);

        $season = CompetitionSeason::create([
            'competition_id' => $competition->id,
            'provider' => 'football_data',
            'external_id' => 2398,
            'year' => 2026,
        ]);

        $home = Team::create(['provider' => 'football_data', 'external_id' => 11, 'name' => 'Home']);
        $away = Team::create(['provider' => 'football_data', 'external_id' => 22, 'name' => 'Away']);

        $base = [
            'provider' => 'football_data',
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'utc_date' => now()->utc()->subDay(),
            'local_date' => now()->timezone('America/Sao_Paulo')->subDay(),
            'home_score_full_time' => 1,
            'away_score_full_time' => 0,
        ];

        $inStage = FootballMatch::create($base + [
            'external_id' => 1001,
            'status' => 'FINISHED',
            'stage' => 'GROUP_STAGE',
        ]);

        $outStage = FootballMatch::create($base + [
            'external_id' => 1002,
            'status' => 'FINISHED',
            'stage' => 'LAST_16',
        ]);

        $notFinished = FootballMatch::create($base + [
            'external_id' => 1003,
            'status' => 'TIMED',
            'stage' => 'GROUP_STAGE',
        ]);

        return [$inStage, $outStage, $notFinished];
    }
}
