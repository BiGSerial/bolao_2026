<?php

namespace Tests\Feature\Domain;

use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\Prediction;
use App\Models\Team;
use App\Models\User;
use App\Services\Predictions\PredictionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_can_save_prediction_and_version_is_created(): void
    {
        [$user, $pool, $match] = $this->makeScenario('active', true, true, '+5 hours');

        $prediction = app(PredictionService::class)->save($pool, $match, $user, 2, 1);

        $this->assertDatabaseHas('predictions', [
            'id' => $prediction->id,
            'home_score' => 2,
            'away_score' => 1,
            'eligible' => 1,
        ]);

        $this->assertDatabaseCount('prediction_versions', 1);
    }

    public function test_pending_member_is_blocked_when_pool_disallows_pending_predictions(): void
    {
        [$user, $pool, $match] = $this->makeScenario('pending', false, true, '+5 hours');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('nao permite palpites de participantes pendentes');

        app(PredictionService::class)->save($pool, $match, $user, 1, 1);
    }

    public function test_prediction_change_is_blocked_when_pool_disallows_changes(): void
    {
        [$user, $pool, $match] = $this->makeScenario('active', true, false, '+5 hours');

        $service = app(PredictionService::class);
        $service->save($pool, $match, $user, 1, 0);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('nao permite alterar palpites');

        $service->save($pool, $match, $user, 2, 0);
    }

    public function test_prediction_is_blocked_inside_lock_window(): void
    {
        [$user, $pool, $match] = $this->makeScenario('active', true, true, '+90 minutes');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Palpite bloqueado');

        app(PredictionService::class)->save($pool, $match, $user, 0, 0);
    }

    public function test_prediction_is_blocked_when_match_is_from_another_competition_or_season(): void
    {
        [$user, $pool] = $this->makeScenario('active', true, true, '+5 hours');

        $otherCompetition = Competition::create([
            'provider' => 'football_data',
            'external_id' => 2013,
            'code' => 'BSA',
            'name' => 'Brasileirao Serie A',
            'type' => 'LEAGUE',
        ]);

        $otherSeason = CompetitionSeason::create([
            'competition_id' => $otherCompetition->id,
            'provider' => 'football_data',
            'external_id' => 9999,
            'year' => 2026,
        ]);

        $home = Team::create(['provider' => 'football_data', 'external_id' => 11, 'name' => 'Home BSA']);
        $away = Team::create(['provider' => 'football_data', 'external_id' => 12, 'name' => 'Away BSA']);

        $match = FootballMatch::create([
            'provider' => 'football_data',
            'external_id' => 8888,
            'competition_id' => $otherCompetition->id,
            'competition_season_id' => $otherSeason->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'utc_date' => now()->utc()->addHours(5),
            'local_date' => now()->timezone('America/Sao_Paulo')->addHours(5),
            'status' => 'TIMED',
            'stage' => 'GROUP_STAGE',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('competição/temporada');

        app(PredictionService::class)->save($pool, $match, $user, 1, 0);
    }

    private function makeScenario(string $memberStatus, bool $allowPending, bool $allowChanges, string $utcDateModifier): array
    {
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

        $home = Team::create(['provider' => 'football_data', 'external_id' => 1, 'name' => 'Home']);
        $away = Team::create(['provider' => 'football_data', 'external_id' => 2, 'name' => 'Away']);

        $match = FootballMatch::create([
            'provider' => 'football_data',
            'external_id' => 100,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'utc_date' => now()->utc()->modify($utcDateModifier),
            'local_date' => now()->timezone('America/Sao_Paulo')->modify($utcDateModifier),
            'status' => 'TIMED',
            'stage' => 'GROUP_STAGE',
        ]);

        $pool = Pool::create([
            'owner_id' => $user->id,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'name' => 'Pool Test',
            'slug' => 'pool-test-'.uniqid(),
            'visibility' => 'invite_only',
            'status' => 'active',
            'invite_code' => strtoupper(substr(uniqid('AB'), 0, 8)),
            'allow_prediction_changes' => $allowChanges,
            'prediction_lock_minutes' => 120,
            'allow_pending_member_predictions' => $allowPending,
            'stage' => 'GROUP_STAGE',
        ]);

        $pool->members()->create([
            'user_id' => $user->id,
            'role' => 'member',
            'status' => $memberStatus,
        ]);

        return [$user, $pool, $match];
    }
}
