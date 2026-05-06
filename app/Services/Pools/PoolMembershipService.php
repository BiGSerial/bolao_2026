<?php

namespace App\Services\Pools;

use App\Enums\PoolMemberRole;
use App\Enums\PoolMemberStatus;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\User;
use DomainException;

class PoolMembershipService
{
    public function activate(Pool $pool, PoolMember $member, User $actor): void
    {
        $this->assertCanManage($pool, $actor);
        $member->activate($actor);
    }

    public function deactivate(Pool $pool, PoolMember $member, User $actor): void
    {
        $this->assertCanManage($pool, $actor);
        $this->assertNotOwner($member);
        $member->deactivate($actor);
    }

    public function remove(Pool $pool, PoolMember $member, User $actor): void
    {
        $this->assertCanManage($pool, $actor);
        $this->assertNotOwner($member);

        $member->update([
            'status' => PoolMemberStatus::Removed->value,
            'deactivated_by' => $actor->id,
            'deactivated_at' => now(),
        ]);
    }

    public function block(Pool $pool, PoolMember $member, User $actor): void
    {
        $this->assertCanManage($pool, $actor);
        $this->assertNotOwner($member);

        $member->update([
            'status' => PoolMemberStatus::Blocked->value,
            'deactivated_by' => $actor->id,
            'deactivated_at' => now(),
        ]);
    }

    public function promoteToManager(Pool $pool, PoolMember $member, User $actor): void
    {
        $this->assertCanManage($pool, $actor);
        $this->assertNotOwner($member);
        $member->update(['role' => PoolMemberRole::Manager->value]);
    }

    public function demoteToMember(Pool $pool, PoolMember $member, User $actor): void
    {
        $this->assertCanManage($pool, $actor);
        $this->assertNotOwner($member);
        $member->update(['role' => PoolMemberRole::Member->value]);
    }

    public function assertCanManage(Pool $pool, User $actor): void
    {
        $manager = $pool->members()->where('user_id', $actor->id)->first();

        if (! $manager || ! in_array($manager->role, [PoolMemberRole::Owner->value, PoolMemberRole::Manager->value], true)) {
            throw new DomainException('Usuario nao possui permissao para gerenciar membros deste bolao.');
        }
    }

    private function assertNotOwner(PoolMember $member): void
    {
        if ($member->role === PoolMemberRole::Owner->value) {
            throw new DomainException('Nao e permitido alterar o membro owner do bolao.');
        }
    }
}
