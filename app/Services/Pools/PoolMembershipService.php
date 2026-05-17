<?php

namespace App\Services\Pools;

use App\Events\PoolMembersUpdated;
use App\Enums\PoolMemberRole;
use App\Enums\PoolMemberStatus;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class PoolMembershipService
{
    public function activate(Pool $pool, PoolMember $member, User $actor): void
    {
        $this->assertCanManage($pool, $actor);
        $member->activate($actor);
        PoolMembersUpdated::dispatch($pool);
    }

    public function deactivate(Pool $pool, PoolMember $member, User $actor): void
    {
        $this->assertCanManage($pool, $actor);
        $this->assertNotOwner($member);
        $member->deactivate($actor);
        PoolMembersUpdated::dispatch($pool);
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
        PoolMembersUpdated::dispatch($pool);
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
        PoolMembersUpdated::dispatch($pool);
    }

    public function promoteToManager(Pool $pool, PoolMember $member, User $actor): void
    {
        $this->assertCanManage($pool, $actor);
        $this->assertNotOwner($member);
        $targetUser = $member->user ?: User::query()->find($member->user_id);
        if ($targetUser && $targetUser->reachedManagedPoolsLimit()) {
            throw new DomainException('Este usuário já atingiu o limite de 5 bolões entre criação e moderação.');
        }
        $member->update(['role' => PoolMemberRole::Manager->value]);
        PoolMembersUpdated::dispatch($pool);
    }

    public function demoteToMember(Pool $pool, PoolMember $member, User $actor): void
    {
        $this->assertCanManage($pool, $actor);
        $this->assertNotOwner($member);
        $member->update(['role' => PoolMemberRole::Member->value]);
        PoolMembersUpdated::dispatch($pool);
    }

    public function transferOwnership(Pool $pool, PoolMember $member, User $actor): void
    {
        $this->assertOwnerActor($pool, $actor);
        $this->assertNotOwner($member);

        $currentOwner = $pool->members()
            ->where('role', PoolMemberRole::Owner->value)
            ->first();

        if (! $currentOwner || (int) $currentOwner->id === (int) $member->id) {
            throw new DomainException('Transferencia de propriedade invalida.');
        }

        DB::transaction(function () use ($currentOwner, $member) {
            $currentOwner->update(['role' => PoolMemberRole::Manager->value]);
            $member->update(['role' => PoolMemberRole::Owner->value]);
        });

        PoolMembersUpdated::dispatch($pool);
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

    private function assertOwnerActor(Pool $pool, User $actor): void
    {
        $owner = $pool->members()
            ->where('user_id', $actor->id)
            ->where('role', PoolMemberRole::Owner->value)
            ->first();

        if (! $owner) {
            throw new DomainException('Apenas o dono do bolao pode transferir propriedade.');
        }
    }
}
