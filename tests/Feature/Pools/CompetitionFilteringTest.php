<?php

namespace Tests\Feature\Pools;

use App\Livewire\Pools\PoolCreate;
use App\Livewire\Pools\PoolIndex;
use App\Models\Competition;
use App\Models\CompetitionPackage;
use App\Models\CompetitionPackageItem;
use App\Models\CompetitionSeason;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CompetitionFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_pool_index_filters_memberships_by_selected_competition(): void
    {
        [$wc, $wcSeason] = $this->createCompetition('WC', 2000, 2026, 'GROUP_STAGE');
        [$bsa, $bsaSeason] = $this->createCompetition('BSA', 2013, 2026, 'REGULAR_SEASON');

        $user = User::factory()->create(['status' => 'active', 'subscription_tier' => 2]);

        $wcPool = $this->createPool($user->id, $wc->id, $wcSeason->id, 'Bolao Copa', 'GROUP_STAGE');
        $bsaPool = $this->createPool($user->id, $bsa->id, $bsaSeason->id, 'Bolao Brasileirao', 'REGULAR_SEASON');

        $wcPool->members()->create(['user_id' => $user->id, 'role' => 'member', 'status' => 'active']);
        $bsaPool->members()->create(['user_id' => $user->id, 'role' => 'member', 'status' => 'active']);

        Livewire::actingAs($user)
            ->withQueryParams(['competition' => 'WC'])
            ->test(PoolIndex::class)
            ->assertSet('competition_code', 'WC')
            ->assertSee('Bolao Copa')
            ->assertDontSee('Bolao Brasileirao')
            ->set('competition_code', 'BSA')
            ->assertSee('Bolao Brasileirao')
            ->assertDontSee('Bolao Copa');
    }

    public function test_basic_user_forcing_bsa_query_falls_back_to_wc(): void
    {
        $this->createCompetition('WC', 2000, 2026, 'GROUP_STAGE');
        $this->createCompetition('BSA', 2013, 2026, 'REGULAR_SEASON');

        $user = User::factory()->create(['status' => 'active', 'subscription_tier' => 1]);

        Livewire::actingAs($user)
            ->withQueryParams(['competition' => 'BSA'])
            ->test(PoolIndex::class)
            ->assertSet('competition_code', 'WC')
            ->assertDontSee('BSA');
    }

    public function test_pool_create_persists_selected_competition_context(): void
    {
        [$wc, $wcSeason] = $this->createCompetition('WC', 2000, 2026, 'GROUP_STAGE');
        [$bsa, $bsaSeason] = $this->createCompetition('BSA', 2013, 2026, 'REGULAR_SEASON');

        config()->set('football-data.default_competition_code', 'WC');
        config()->set('football-data.competitions.WC.season', 2026);
        config()->set('football-data.competitions.WC.default_stage', 'GROUP_STAGE');
        config()->set('football-data.competitions.WC.enabled', true);
        config()->set('football-data.competitions.WC.name', 'Copa do Mundo');
        config()->set('football-data.competitions.BSA.season', 2026);
        config()->set('football-data.competitions.BSA.default_stage', 'REGULAR_SEASON');
        config()->set('football-data.competitions.BSA.enabled', true);
        config()->set('football-data.competitions.BSA.name', 'Brasileirao Serie A');

        $user = User::factory()->create(['status' => 'active', 'subscription_tier' => 2]);

        Livewire::actingAs($user)
            ->withQueryParams(['competition' => 'BSA'])
            ->test(PoolCreate::class)
            ->set('competition_code', 'BSA')
            ->set('name', 'Bolao BSA Teste')
            ->set('description', 'Descricao')
            ->set('visibility', 'invite_only')
            ->set('prediction_lock_minutes', 30)
            ->set('allow_prediction_changes', true)
            ->set('allow_pending_member_predictions', true)
            ->set('points_exact_score', 5)
            ->set('points_correct_result', 3)
            ->set('points_correct_goals', 1)
            ->call('save')
            ->assertHasNoErrors();

        $pool = Pool::query()->where('name', 'Bolao BSA Teste')->firstOrFail();

        $this->assertSame($bsa->id, $pool->competition_id);
        $this->assertSame($bsaSeason->id, $pool->competition_season_id);
        $this->assertSame('REGULAR_SEASON', $pool->stage);

        $this->assertDatabaseHas('pool_members', [
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        // Sanity check to avoid unused vars in future edits
        $this->assertNotNull($wc);
        $this->assertNotNull($wcSeason);
    }

    public function test_pool_create_hides_bsa_for_basic_user_with_basic_package(): void
    {
        $this->createCompetition('WC', 2000, 2026, 'GROUP_STAGE');
        $this->createCompetition('BSA', 2013, 2026, 'REGULAR_SEASON');

        config()->set('football-data.competitions.WC.enabled', true);
        config()->set('football-data.competitions.WC.name', 'Copa do Mundo');
        config()->set('football-data.competitions.BSA.enabled', true);
        config()->set('football-data.competitions.BSA.name', 'Brasileirao Serie A');

        $basic = CompetitionPackage::create([
            'code' => 'basic',
            'name' => 'Plano Básico',
            'active' => true,
        ]);

        CompetitionPackageItem::create([
            'competition_package_id' => $basic->id,
            'competition_code' => 'WC',
        ]);

        $user = User::factory()->create([
            'status' => 'active',
            'subscription_tier' => 2,
            'competition_package_id' => $basic->id,
        ]);

        Livewire::actingAs($user)
            ->test(PoolCreate::class)
            ->assertSee('Copa do Mundo')
            ->assertDontSee('Brasileirao Serie A');
    }

    private function createCompetition(string $code, int $externalId, int $year, string $defaultStage): array
    {
        $competition = Competition::create([
            'provider' => 'football_data',
            'external_id' => $externalId,
            'code' => $code,
            'name' => $code,
            'type' => $code === 'WC' ? 'CUP' : 'LEAGUE',
        ]);

        $season = CompetitionSeason::create([
            'competition_id' => $competition->id,
            'provider' => 'football_data',
            'external_id' => $externalId * 10,
            'year' => $year,
        ]);

        config()->set('football-data.competitions.'.$code.'.season', $year);
        config()->set('football-data.competitions.'.$code.'.default_stage', $defaultStage);
        config()->set('football-data.competitions.'.$code.'.enabled', true);

        return [$competition, $season];
    }

    private function createPool(int $ownerId, int $competitionId, int $seasonId, string $name, string $stage): Pool
    {
        return Pool::create([
            'owner_id' => $ownerId,
            'competition_id' => $competitionId,
            'competition_season_id' => $seasonId,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'visibility' => 'invite_only',
            'status' => 'active',
            'invite_code' => strtoupper(Str::random(8)),
            'allow_prediction_changes' => true,
            'prediction_lock_minutes' => 30,
            'allow_pending_member_predictions' => true,
            'stage' => $stage,
            'points_exact_score' => 5,
            'points_correct_result' => 3,
            'points_correct_goals' => 1,
        ]);
    }
}
