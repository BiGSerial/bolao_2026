<?php

namespace Tests\Feature\Domain;

use App\Jobs\CalculatePredictionsForMatchJob;
use App\Jobs\RecalculatePoolRankingsJob;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\Prediction;
use App\Models\Team;
use App\Models\User;
use App\Services\FootballData\FootballDataClient;
use App\Services\FootballData\SyncWorldCupMatchDetailsService;
use App\Services\FootballData\SyncWorldCupMatchesService;
use App\Services\FootballData\SyncWorldCupStandingsService;
use App\Services\Predictions\PredictionScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ScoringAndSyncRankingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoring_job_calculates_predictions_and_dispatches_ranking_for_affected_pools(): void
    {
        Bus::fake();

        [$match, $pool, $user] = $this->makePoolFixture('GROUP_STAGE', 'GROUP_STAGE');

        Prediction::create([
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'football_match_id' => $match->id,
            'home_score' => 2,
            'away_score' => 1,
            'points' => 0,
            'eligible' => true,
            'last_changed_at' => null,
        ]);

        (new CalculatePredictionsForMatchJob($match->id))->handle(app(PredictionScoringService::class));

        $prediction = Prediction::query()->firstOrFail();
        $this->assertSame(5, (int) $prediction->points);
        $this->assertNotNull($prediction->calculated_at);

        Bus::assertDispatched(RecalculatePoolRankingsJob::class, function (RecalculatePoolRankingsJob $job) use ($pool): bool {
            return $job->poolId === $pool->id;
        });
    }

    public function test_group_stage_sync_dispatches_scoring_without_direct_ranking_dispatch(): void
    {
        Bus::fake();

        $match = $this->makeFinishedMatch('GROUP_STAGE');

        $client = $this->mock(FootballDataClient::class);
        $client->shouldReceive('competitionContext')->once()->andReturn([
            'code' => 'WC',
            'season' => 2026,
            'stage' => 'GROUP_STAGE',
        ]);
        $client->shouldReceive('competitionMatches')->once()->andReturn(['matches' => []]);
        $client->shouldReceive('competitionStandings')->once()->andReturn(['standings' => []]);

        $syncService = $this->mock(SyncWorldCupMatchesService::class);
        $syncService->shouldReceive('sync')->once()->andReturn(collect([$match]));

        $standingsService = $this->mock(SyncWorldCupStandingsService::class);
        $standingsService->shouldReceive('sync')->once()->andReturn(0);

        $this->artisan('worldcup:sync-group-stage', ['--force' => true])->assertSuccessful();

        Bus::assertDispatched(CalculatePredictionsForMatchJob::class, fn (CalculatePredictionsForMatchJob $job): bool => $job->footballMatchId === $match->id);
        Bus::assertNotDispatched(RecalculatePoolRankingsJob::class);
    }

    public function test_match_details_sync_dispatches_scoring_for_finished_changed_matches(): void
    {
        Bus::fake();

        $client = $this->mock(FootballDataClient::class);
        $client->shouldReceive('competitionContext')->once()->andReturn([
            'code' => 'WC',
            'season' => 2026,
            'stage' => 'GROUP_STAGE',
        ]);
        $client->shouldReceive('competitionStandings')->once()->andReturn(['standings' => []]);

        $detailsService = $this->mock(SyncWorldCupMatchDetailsService::class);
        $detailsService->shouldReceive('syncBatch')->once()->andReturn([
            'selected' => 2,
            'updated' => 1,
            'errors' => 0,
            'enriched' => 0,
            'api_football_requests' => 0,
            'api_football_failures' => 0,
            'api_football_sync_type' => 'not_used',
            'sync_mode' => 'batch',
            'finished_changed_match_ids' => [101, 101, 202],
        ]);

        $standingsService = $this->mock(SyncWorldCupStandingsService::class);
        $standingsService->shouldReceive('sync')->once()->andReturn(0);

        $this->artisan('worldcup:sync-match-details')->assertSuccessful();

        Bus::assertDispatched(CalculatePredictionsForMatchJob::class, 2);
        Bus::assertDispatched(CalculatePredictionsForMatchJob::class, fn (CalculatePredictionsForMatchJob $job): bool => $job->footballMatchId === 101);
        Bus::assertDispatched(CalculatePredictionsForMatchJob::class, fn (CalculatePredictionsForMatchJob $job): bool => $job->footballMatchId === 202);
        Bus::assertNotDispatched(RecalculatePoolRankingsJob::class);
    }

    private function makePoolFixture(string $poolStage, string $matchStage): array
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
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

        $pool = Pool::create([
            'owner_id' => $owner->id,
            'name' => 'Pool '.uniqid(),
            'slug' => 'pool-'.uniqid(),
            'visibility' => 'invite_only',
            'status' => 'active',
            'invite_code' => strtoupper(substr(uniqid('GH'), 0, 8)),
            'allow_prediction_changes' => true,
            'prediction_lock_minutes' => 120,
            'allow_pending_member_predictions' => true,
            'stage' => $poolStage,
        ]);
        $pool->members()->create(['user_id' => $user->id, 'role' => 'member', 'status' => 'active']);

        $match = FootballMatch::create([
            'provider' => 'football_data',
            'external_id' => random_int(1000, 9999),
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'utc_date' => now()->utc()->subDay(),
            'local_date' => now()->timezone('America/Sao_Paulo')->subDay(),
            'status' => 'FINISHED',
            'stage' => $matchStage,
            'home_score_full_time' => 2,
            'away_score_full_time' => 1,
        ]);

        return [$match, $pool, $user];
    }

    private function makeFinishedMatch(string $stage): FootballMatch
    {
        [$match] = $this->makePoolFixture($stage, $stage);
        return $match;
    }
}
