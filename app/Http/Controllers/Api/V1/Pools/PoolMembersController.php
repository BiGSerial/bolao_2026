<?php

namespace App\Http\Controllers\Api\V1\Pools;

use App\Enums\PoolMemberRole;
use App\Enums\PoolMemberStatus;
use App\Http\Controllers\Controller;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\User;
use App\Notifications\PoolMemberApprovedNotification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoolMembersController extends Controller
{
    public function index(Request $request, Pool $pool): JsonResponse
    {
        if (! $this->canManagePool($pool, $request->user()->id, (bool) $request->user()->is_admin)) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado para gerenciar membros.', 403);
        }

        $members = PoolMember::query()
            ->where('pool_id', $pool->id)
            ->with('user:id,name,email')
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderByRaw("case role when 'owner' then 0 when 'manager' then 1 else 2 end")
            ->orderBy('id')
            ->get();

        return ApiResponse::success($request, [
            'pool_id' => $pool->id,
            'items' => $members->map(fn (PoolMember $m): array => [
                'id' => $m->id,
                'user' => [
                    'id' => $m->user?->id,
                    'name' => $m->user?->name,
                    'email' => $m->user?->email,
                ],
                'role' => (string) $m->role,
                'status' => (string) $m->status,
                'sector' => $m->sector,
                'activated_at' => optional($m->activated_at)?->toIso8601String(),
                'deactivated_at' => optional($m->deactivated_at)?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function update(Request $request, Pool $pool, PoolMember $member): JsonResponse
    {
        if ((int) $member->pool_id !== (int) $pool->id) {
            return ApiResponse::error($request, 'MEMBER_NOT_FOUND', 'Membro não encontrado neste bolão.', 404);
        }

        if (! $this->canManagePool($pool, $request->user()->id, (bool) $request->user()->is_admin)) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado para gerenciar membros.', 403);
        }

        $validated = $request->validate([
            'role' => ['nullable', 'in:manager,member'],
            'status' => ['nullable', 'in:pending,active,inactive,blocked'],
            'sector' => ['nullable', 'string', 'max:80'],
        ]);

        if ((string) $member->role === PoolMemberRole::Owner->value) {
            return ApiResponse::error($request, 'OWNER_IMMUTABLE', 'Não é permitido alterar o dono do bolão por esta ação.', 409);
        }

        $updates = [];
        if (isset($validated['role'])) {
            $updates['role'] = $validated['role'];
        }
        if (array_key_exists('sector', $validated)) {
            $updates['sector'] = trim((string) $validated['sector']) !== '' ? trim((string) $validated['sector']) : null;
        }
        $wasApproved = false;
        if (isset($validated['status'])) {
            $updates['status'] = $validated['status'];
            if ($validated['status'] === PoolMemberStatus::Active->value) {
                $updates['activated_by'] = $request->user()->id;
                $updates['activated_at'] = now();
                $updates['deactivated_by'] = null;
                $updates['deactivated_at'] = null;
                $wasApproved = (string) $member->status === PoolMemberStatus::Pending->value;
            } elseif (in_array($validated['status'], [PoolMemberStatus::Inactive->value, PoolMemberStatus::Blocked->value], true)) {
                $updates['deactivated_by'] = $request->user()->id;
                $updates['deactivated_at'] = now();
            }
        }

        if ($updates !== []) {
            $member->update($updates);
        }

        if ($wasApproved && $member->user_id) {
            $user = User::query()->find($member->user_id);
            $user?->notify(new PoolMemberApprovedNotification($pool));
        }

        $member->refresh()->load('user:id,name,email');

        return ApiResponse::success($request, [
            'member' => [
                'id' => $member->id,
                'user' => [
                    'id' => $member->user?->id,
                    'name' => $member->user?->name,
                    'email' => $member->user?->email,
                ],
                'role' => (string) $member->role,
                'status' => (string) $member->status,
                'sector' => $member->sector,
            ],
        ]);
    }

    public function destroy(Request $request, Pool $pool, PoolMember $member): JsonResponse
    {
        if ((int) $member->pool_id !== (int) $pool->id) {
            return ApiResponse::error($request, 'MEMBER_NOT_FOUND', 'Membro não encontrado neste bolão.', 404);
        }

        if (! $this->canManagePool($pool, $request->user()->id, (bool) $request->user()->is_admin)) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado para gerenciar membros.', 403);
        }

        if ((string) $member->role === PoolMemberRole::Owner->value) {
            return ApiResponse::error($request, 'OWNER_IMMUTABLE', 'Não é permitido remover o dono do bolão.', 409);
        }

        $member->update([
            'status' => PoolMemberStatus::Removed->value,
            'deactivated_by' => $request->user()->id,
            'deactivated_at' => now(),
        ]);

        return ApiResponse::success($request, [
            'pool_id' => $pool->id,
            'member_id' => $member->id,
            'status' => 'removed',
        ]);
    }

    private function canManagePool(Pool $pool, int $userId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }

        return $pool->members()
            ->where('user_id', $userId)
            ->whereIn('role', [PoolMemberRole::Owner->value, PoolMemberRole::Manager->value])
            ->whereIn('status', [PoolMemberStatus::Active->value, PoolMemberStatus::Pending->value])
            ->exists();
    }
}

