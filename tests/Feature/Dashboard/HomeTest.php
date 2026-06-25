<?php

namespace Tests\Feature\Dashboard;

use App\Livewire\Dashboard\Home;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\Prediction;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_when_current_user_is_outside_top_five_live_ranking(): void
    {
        config()->set('football-data.default_competition_code', 'WC');
        config()->set('football-data.competitions.WC.season', 2026);
        config()->set('football-data.competitions.WC.default_stage', 'GROUP_STAGE');
        config()->set('football-data.competitions.WC.enabled', true);
        config()->set('football-data.competitions.WC.name', 'Copa do Mundo');

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

        $owner = User::factory()->create(['name' => 'Owner']);
        $currentUser = User::factory()->create(['name' => 'Z Current User', 'display_name' => 'Current']);
        $topUsers = collect(range(1, 5))
            ->map(fn (int $index) => User::factory()->create([
                'name' => 'Top User '.$index,
                'display_name' => 'Top '.$index,
            ]));

        $pool = Pool::create([
            'owner_id' => $owner->id,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'name' => 'Bolao Dashboard',
            'slug' => 'bolao-dashboard',
            'visibility' => 'invite_only',
            'status' => 'active',
            'invite_code' => 'DASH'.uniqid(),
            'allow_prediction_changes' => true,
            'prediction_lock_minutes' => 120,
            'allow_pending_member_predictions' => true,
            'stage' => 'GROUP_STAGE',
            'points_exact_score' => 5,
            'points_correct_result' => 3,
            'points_correct_goals' => 1,
            'correct_goals_mode' => 'both_teams',
        ]);

        $topUsers
            ->concat([$currentUser])
            ->each(fn (User $user) => $pool->members()->create([
                'user_id' => $user->id,
                'role' => 'member',
                'status' => 'active',
            ]));

        $home = Team::create(['provider' => 'football_data', 'external_id' => 11, 'name' => 'Home']);
        $away = Team::create(['provider' => 'football_data', 'external_id' => 22, 'name' => 'Away']);

        $match = FootballMatch::create([
            'provider' => 'football_data',
            'external_id' => 1001,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'utc_date' => now()->utc()->subDay(),
            'local_date' => now()->timezone('America/Sao_Paulo')->subDay(),
            'status' => 'FINISHED',
            'stage' => 'GROUP_STAGE',
            'home_score_full_time' => 1,
            'away_score_full_time' => 0,
        ]);

        $topUsers->each(fn (User $user) => Prediction::create([
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'football_match_id' => $match->id,
            'home_score' => 1,
            'away_score' => 0,
            'eligible' => true,
            'last_changed_at' => now()->subHours(4),
        ]));

        Prediction::create([
            'pool_id' => $pool->id,
            'user_id' => $currentUser->id,
            'football_match_id' => $match->id,
            'home_score' => 0,
            'away_score' => 0,
            'eligible' => true,
            'last_changed_at' => now()->subHours(4),
        ]);

        Livewire::actingAs($currentUser)
            ->withQueryParams(['competition' => 'WC', 'pool' => $pool->id])
            ->test(Home::class)
            ->assertOk()
            ->assertSee('Current')
            ->assertSee('6º');
    }
}
