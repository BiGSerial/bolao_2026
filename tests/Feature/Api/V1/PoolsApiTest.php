<?php

namespace Tests\Feature\Api\V1;

use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PoolsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_returns_member_and_discoverable_pools(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        [$competition, $season] = $this->baseCompetition();

        $memberPool = Pool::query()->create([
            'owner_id' => $user->id,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'name' => 'Meu Bolao',
            'slug' => 'meu-bolao',
            'visibility' => 'invite_only',
            'status' => 'active',
            'invite_code' => Str::upper(Str::random(10)),
        ]);

        PoolMember::query()->create([
            'pool_id' => $memberPool->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        Pool::query()->create([
            'owner_id' => User::factory()->create()->id,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'name' => 'Publico Aberto',
            'slug' => 'publico-aberto',
            'visibility' => 'public',
            'status' => 'active',
            'invite_code' => Str::upper(Str::random(10)),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/pools');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.member_pools')
            ->assertJsonCount(1, 'data.discoverable_pools')
            ->assertJsonPath('data.member_pools.0.id', $memberPool->id);
    }

    public function test_show_forbidden_when_user_has_no_access_and_pool_not_public(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        [$competition, $season] = $this->baseCompetition();

        $privatePool = Pool::query()->create([
            'owner_id' => User::factory()->create()->id,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'name' => 'Privado',
            'slug' => 'privado',
            'visibility' => 'invite_only',
            'status' => 'active',
            'invite_code' => Str::upper(Str::random(10)),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/pools/'.$privatePool->id);

        $response
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'POOL_FORBIDDEN');
    }

    private function baseCompetition(): array
    {
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

        return [$competition, $season];
    }
}
