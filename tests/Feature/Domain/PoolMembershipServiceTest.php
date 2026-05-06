<?php

namespace Tests\Feature\Domain;

use App\Models\Pool;
use App\Models\User;
use App\Services\Pools\PoolMembershipService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoolMembershipServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_activate_pending_member(): void
    {
        [$owner, $pool, $member] = $this->makePoolWithMembers('pending');

        app(PoolMembershipService::class)->activate($pool, $member, $owner);

        $member->refresh();

        $this->assertSame('active', $member->status);
        $this->assertNotNull($member->activated_at);
        $this->assertSame($owner->id, $member->activated_by);
    }

    public function test_non_manager_cannot_manage_members(): void
    {
        [$owner, $pool, $member, $randomUser] = $this->makePoolWithMembers('pending', withRandomUser: true);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('nao possui permissao');

        app(PoolMembershipService::class)->activate($pool, $member, $randomUser);
    }

    public function test_owner_member_cannot_be_removed(): void
    {
        $owner = User::factory()->create();

        $pool = Pool::create([
            'owner_id' => $owner->id,
            'name' => 'Pool Owner',
            'slug' => 'pool-owner-'.uniqid(),
            'visibility' => 'invite_only',
            'status' => 'active',
            'invite_code' => strtoupper(substr(uniqid('CD'), 0, 8)),
            'allow_prediction_changes' => true,
            'prediction_lock_minutes' => 120,
            'allow_pending_member_predictions' => true,
            'stage' => 'GROUP_STAGE',
        ]);

        $ownerMember = $pool->members()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('owner do bolao');

        app(PoolMembershipService::class)->remove($pool, $ownerMember, $owner);
    }

    private function makePoolWithMembers(string $memberStatus, bool $withRandomUser = false): array
    {
        $owner = User::factory()->create();
        $memberUser = User::factory()->create();

        $pool = Pool::create([
            'owner_id' => $owner->id,
            'name' => 'Pool Members',
            'slug' => 'pool-members-'.uniqid(),
            'visibility' => 'invite_only',
            'status' => 'active',
            'invite_code' => strtoupper(substr(uniqid('EF'), 0, 8)),
            'allow_prediction_changes' => true,
            'prediction_lock_minutes' => 120,
            'allow_pending_member_predictions' => true,
            'stage' => 'GROUP_STAGE',
        ]);

        $pool->members()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'activated_by' => $owner->id,
            'activated_at' => now(),
        ]);

        $member = $pool->members()->create([
            'user_id' => $memberUser->id,
            'role' => 'member',
            'status' => $memberStatus,
        ]);

        $randomUser = $withRandomUser ? User::factory()->create() : null;

        return [$owner, $pool, $member, $randomUser];
    }
}
