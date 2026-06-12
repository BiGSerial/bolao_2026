<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RunCompetitionSyncJob;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FootballMatch;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RunCompetitionSyncJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_sync_is_skipped_during_post_kickoff_live_window(): void
    {
        $this->createMatch(now()->utc()->subMinutes(30)->format('Y-m-d H:i:s'), 'TIMED');

        Artisan::shouldReceive('call')->never();

        (new RunCompetitionSyncJob('WC', 2026, 'GROUP_STAGE'))->handle();
    }

    public function test_free_sync_runs_when_there_is_no_live_or_post_kickoff_match(): void
    {
        $this->createMatch(now()->utc()->addHours(5)->format('Y-m-d H:i:s'), 'TIMED');

        Artisan::shouldReceive('call')
            ->once()
            ->with('worldcup:sync-group-stage', [
                '--code' => 'WC',
                '--season' => 2026,
                '--stage' => 'GROUP_STAGE',
                '--force' => true,
            ])
            ->andReturn(0);

        (new RunCompetitionSyncJob('WC', 2026, 'GROUP_STAGE'))->handle();
    }

    private function createMatch(string $utcDate, string $status): FootballMatch
    {
        $competition = Competition::create([
            'provider' => 'football_data',
            'external_id' => 2000,
            'code' => 'WC',
            'name' => 'World Cup',
        ]);
        $season = CompetitionSeason::create([
            'competition_id' => $competition->id,
            'provider' => 'football_data',
            'external_id' => 2398,
            'year' => 2026,
        ]);
        $home = Team::create([
            'provider' => 'football_data',
            'external_id' => 1,
            'name' => 'Home',
        ]);
        $away = Team::create([
            'provider' => 'football_data',
            'external_id' => 2,
            'name' => 'Away',
        ]);

        return FootballMatch::create([
            'provider' => 'football_data',
            'external_id' => 5001,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'utc_date' => $utcDate,
            'local_date' => now('America/Sao_Paulo'),
            'status' => $status,
            'stage' => 'GROUP_STAGE',
        ]);
    }
}
