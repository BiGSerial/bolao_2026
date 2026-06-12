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
use App\Services\Pools\PoolPredictionsPdfData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PoolPredictionsPdfApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_can_download_branded_predictions_pdf(): void
    {
        [$user, $pool] = $this->scenario();
        $token = $user->createToken('pdf-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get("/api/v1/pools/{$pool->id}/predictions/me.pdf");

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_non_member_cannot_download_pool_predictions_pdf(): void
    {
        [, $pool] = $this->scenario();
        $other = User::factory()->create();
        $token = $other->createToken('pdf-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get("/api/v1/pools/{$pool->id}/predictions/me.pdf")
            ->assertForbidden();
    }

    public function test_pdf_data_lists_every_pool_match_even_without_prediction(): void
    {
        [$user, $pool] = $this->scenario();

        $data = app(PoolPredictionsPdfData::class)->build($pool, $user);

        $this->assertCount(2, $data['matches']);
        $this->assertSame('2 x 1', $data['matches'][0]['prediction']);
        $this->assertSame('—', $data['matches'][1]['prediction']);
        $this->assertSame('____ x ____', $data['matches'][1]['result']);
        $this->assertSame('____', $data['matches'][1]['points']);
    }

    private function scenario(): array
    {
        $user = User::factory()->create(['display_name' => 'Participante']);
        $competition = Competition::query()->create([
            'provider' => 'football_data',
            'external_id' => 9901,
            'code' => 'WC',
            'name' => 'World Cup',
            'type' => 'CUP',
        ]);
        $season = CompetitionSeason::query()->create([
            'competition_id' => $competition->id,
            'provider' => 'football_data',
            'external_id' => 9926,
            'year' => 2026,
        ]);
        $home = Team::query()->create([
            'provider' => 'football_data',
            'external_id' => 9101,
            'name' => 'Brazil',
            'short_name' => 'Brazil',
            'tla' => 'BRA',
        ]);
        $away = Team::query()->create([
            'provider' => 'football_data',
            'external_id' => 9102,
            'name' => 'France',
            'short_name' => 'France',
            'tla' => 'FRA',
        ]);
        $pool = Pool::query()->create([
            'owner_id' => $user->id,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'name' => 'Bolão Documento',
            'slug' => 'bolao-documento',
            'visibility' => 'invite_only',
            'status' => 'active',
            'stage' => 'GROUP_STAGE',
            'invite_code' => Str::upper(Str::random(10)),
            'points_exact_score' => 5,
            'points_correct_result' => 3,
            'points_correct_goals' => 1,
            'prediction_lock_minutes' => 30,
        ]);
        PoolMember::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        $match = FootballMatch::query()->create([
            'provider' => 'football_data',
            'external_id' => 9201,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'utc_date' => now()->utc()->subDay(),
            'status' => 'FINISHED',
            'stage' => 'GROUP_STAGE',
            'group_name' => 'GROUP_A',
            'home_score_full_time' => 2,
            'away_score_full_time' => 1,
        ]);
        Prediction::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'football_match_id' => $match->id,
            'home_score' => 2,
            'away_score' => 1,
            'points' => 5,
            'eligible' => true,
            'calculated_at' => now(),
            'last_changed_at' => now()->subDays(2),
        ]);
        FootballMatch::query()->create([
            'provider' => 'football_data',
            'external_id' => 9202,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $away->id,
            'away_team_id' => $home->id,
            'utc_date' => now()->utc()->addDay(),
            'status' => 'TIMED',
            'stage' => 'GROUP_STAGE',
            'group_name' => 'GROUP_A',
        ]);

        return [$user, $pool];
    }
}
