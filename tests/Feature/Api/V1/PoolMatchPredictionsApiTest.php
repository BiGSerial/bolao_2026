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

class PoolMatchPredictionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_predictions_return_five_user_ranking_window_around_viewer(): void
    {
        [$viewer, $pool, $match] = $this->seedSevenMemberRankingScenario();
        $token = $viewer->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/pools/{$pool->id}/matches/{$match->id}/predictions");

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data.predictions')
            ->assertJsonPath('data.predictions.0.position', 3)
            ->assertJsonPath('data.predictions.1.position', 4)
            ->assertJsonPath('data.predictions.2.position', 5)
            ->assertJsonPath('data.predictions.2.user.id', $viewer->id)
            ->assertJsonPath('data.predictions.2.points_total', 5)
            ->assertJsonPath('data.predictions.3.position', 6)
            ->assertJsonPath('data.predictions.4.position', 7);
    }

    private function seedSevenMemberRankingScenario(): array
    {
        $competition = Competition::query()->create([
            'provider' => 'football_data',
            'external_id' => 2000,
            'code' => 'WC',
            'name' => 'World Cup',
        ]);

        $season = CompetitionSeason::query()->create([
            'competition_id' => $competition->id,
            'provider' => 'football_data',
            'external_id' => 2398,
            'year' => 2026,
        ]);

        $home = Team::query()->create([
            'provider' => 'football_data',
            'external_id' => 11,
            'name' => 'Brazil',
            'short_name' => 'Brazil',
            'tla' => 'BRA',
        ]);

        $away = Team::query()->create([
            'provider' => 'football_data',
            'external_id' => 22,
            'name' => 'France',
            'short_name' => 'France',
            'tla' => 'FRA',
        ]);

        $owner = User::factory()->create(['name' => 'User 1', 'display_name' => 'User 1']);
        $users = collect([$owner]);
        foreach (range(2, 7) as $index) {
            $users->push(User::factory()->create([
                'name' => 'User '.$index,
                'display_name' => 'User '.$index,
            ]));
        }
        $viewer = $users[4];

        $pool = Pool::query()->create([
            'owner_id' => $owner->id,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'name' => 'Pool Match Predictions',
            'slug' => 'pool-match-predictions',
            'visibility' => 'invite_only',
            'status' => 'active',
            'stage' => 'GROUP_STAGE',
            'points_exact_score' => 5,
            'points_correct_result' => 3,
            'points_correct_goals' => 1,
            'invite_code' => Str::upper(Str::random(10)),
        ]);

        $users->each(fn (User $user, int $index) => PoolMember::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'role' => $index === 0 ? 'owner' : 'member',
            'status' => 'active',
        ]));

        $matches = collect(range(1, 5))->map(fn (int $index) => FootballMatch::query()->create([
            'provider' => 'football_data',
            'external_id' => 5000 + $index,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'utc_date' => now()->utc()->subDays(6 - $index),
            'local_date' => now()->timezone('America/Sao_Paulo')->subDays(6 - $index),
            'status' => 'FINISHED',
            'stage' => 'GROUP_STAGE',
            'home_score_full_time' => 1,
            'away_score_full_time' => 0,
        ]));

        $exactCountsByUserIndex = [0 => 5, 1 => 4, 2 => 3, 3 => 2, 4 => 1];
        foreach ($users as $userIndex => $user) {
            foreach ($matches as $matchIndex => $match) {
                if (isset($exactCountsByUserIndex[$userIndex]) && $matchIndex < $exactCountsByUserIndex[$userIndex]) {
                    $scores = [1, 0];
                } elseif ($userIndex === 5 && $matchIndex === 0) {
                    $scores = [2, 0];
                } elseif ($userIndex === 6 && $matchIndex === 0) {
                    $scores = [1, 1];
                } else {
                    continue;
                }

                Prediction::query()->create([
                    'pool_id' => $pool->id,
                    'user_id' => $user->id,
                    'football_match_id' => $match->id,
                    'home_score' => $scores[0],
                    'away_score' => $scores[1],
                    'eligible' => true,
                    'last_changed_at' => now()->subHours(4),
                ]);
            }
        }

        return [$viewer, $pool, $matches->first()];
    }
}
