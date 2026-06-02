<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PoolMemberRole;
use App\Enums\PoolMemberStatus;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Pool;
use App\Models\PoolInvite;
use App\Models\PoolMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PoolMembershipActionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_join_request_for_pool(): void
    {
        [$pool] = $this->basePool();
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/pools/{$pool->id}/join-requests", [
                'sector' => 'Norte',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('pool_members', [
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'status' => PoolMemberStatus::Pending->value,
        ]);
    }

    public function test_user_can_lookup_pool_by_invite_code_before_joining(): void
    {
        [$pool] = $this->basePool(['invite_code' => 'ABCD1234']);
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/pools/lookup-by-code?invite_code=abcd-1234');

        $response
            ->assertOk()
            ->assertJsonPath('data.pool.id', $pool->id)
            ->assertJsonPath('data.pool.name', $pool->name)
            ->assertJsonPath('data.pool.invite_code', 'ABCD1234')
            ->assertJsonPath('data.pool.can_request_join', true)
            ->assertJsonPath('data.pool.membership', null);
    }

    public function test_user_can_join_by_invite_code_with_separators(): void
    {
        [$pool] = $this->basePool(['invite_code' => 'EFGH5678']);
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/pools/join-by-code', [
                'invite_code' => 'efgh 5678',
                'sector' => 'Norte',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.pool_id', $pool->id)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('pool_members', [
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'sector' => 'Norte',
            'status' => PoolMemberStatus::Pending->value,
        ]);
    }

    public function test_manager_can_create_invite(): void
    {
        [$pool, $owner] = $this->basePool();
        $ownerToken = $owner->createToken('owner-device')->plainTextToken;

        $inviteResponse = $this->withHeader('Authorization', 'Bearer '.$ownerToken)
            ->postJson("/api/v1/pools/{$pool->id}/invites", [
                'email' => 'invitee@example.com',
                'sector' => 'Sul',
            ]);

        $inviteResponse
            ->assertStatus(201)
            ->assertJsonPath('data.invite.email', 'invitee@example.com');
    }

    public function test_invited_user_can_accept_invite_token(): void
    {
        [$pool, $owner] = $this->basePool();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invite = PoolInvite::query()->create([
            'pool_id' => $pool->id,
            'invited_by' => $owner->id,
            'email' => 'invitee@example.com',
            'sector' => 'Sul',
            'token' => Str::random(48),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $inviteeAuthToken = $invitee->createToken('invitee-device')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$inviteeAuthToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $invitee->id);

        $acceptResponse = $this->withHeader('Authorization', 'Bearer '.$inviteeAuthToken)
            ->postJson('/api/v1/pools/invites/'.$invite->token.'/accept');

        $acceptResponse
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.pool.id', $pool->id);

        $this->assertDatabaseHas('pool_members', [
            'pool_id' => $pool->id,
            'user_id' => $invitee->id,
            'status' => PoolMemberStatus::Active->value,
            'role' => PoolMemberRole::Member->value,
        ]);

        $this->assertDatabaseHas('pool_invites', [
            'token' => $invite->token,
            'status' => 'accepted',
            'accepted_by' => $invitee->id,
        ]);
    }

    public function test_non_manager_cannot_create_invite(): void
    {
        [$pool] = $this->basePool();
        $user = User::factory()->create();

        PoolMember::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $user->id,
            'role' => PoolMemberRole::Member->value,
            'status' => PoolMemberStatus::Active->value,
        ]);

        $token = $user->createToken('member-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/pools/{$pool->id}/invites", [
                'email' => 'test@example.com',
            ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'POOL_FORBIDDEN');
    }

    private function basePool(array $overrides = []): array
    {
        $owner = User::factory()->create();

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

        $pool = Pool::query()->create(array_merge([
            'owner_id' => $owner->id,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'name' => 'Pool Actions',
            'slug' => 'pool-actions-'.Str::lower(Str::random(5)),
            'visibility' => 'invite_only',
            'status' => 'active',
            'sectors' => ['Norte', 'Sul'],
            'invite_code' => Str::upper(Str::random(8)),
        ], $overrides));

        PoolMember::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $owner->id,
            'role' => PoolMemberRole::Owner->value,
            'status' => PoolMemberStatus::Active->value,
        ]);

        return [$pool, $owner];
    }
}
