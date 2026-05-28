<?php

namespace App\Http\Controllers\Api\V1\Pools;

use App\Enums\PoolMemberRole;
use App\Enums\PoolMemberStatus;
use App\Http\Controllers\Controller;
use App\Models\Pool;
use App\Models\PoolInvite;
use App\Models\PoolMember;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PoolMembershipActionsController extends Controller
{
    public function joinRequest(Request $request, Pool $pool): JsonResponse
    {
        $user = $request->user();

        if ((string) $pool->status !== 'active') {
            return ApiResponse::error($request, 'POOL_UNAVAILABLE', 'Este bolão não está ativo.', 409);
        }

        $validated = $request->validate([
            'sector' => ['nullable', 'string', 'max:80'],
        ]);

        $allowedSectors = is_array($pool->sectors) ? array_values($pool->sectors) : [];
        $sector = trim((string) ($validated['sector'] ?? ''));

        if ($sector !== '' && ! in_array($sector, $allowedSectors, true)) {
            return ApiResponse::error($request, 'INVALID_SECTOR', 'Setor inválido para este bolão.', 422, [
                'fields' => ['sector' => ['Setor inválido para este bolão.']],
            ]);
        }

        $member = PoolMember::query()
            ->where('pool_id', $pool->id)
            ->where('user_id', $user->id)
            ->first();

        if ($member && $member->status === PoolMemberStatus::Active->value) {
            return ApiResponse::success($request, [
                'pool_id' => $pool->id,
                'status' => 'already_active',
                'message' => 'Você já participa deste bolão.',
            ]);
        }

        if ($member && $member->status === PoolMemberStatus::Pending->value) {
            return ApiResponse::success($request, [
                'pool_id' => $pool->id,
                'status' => 'already_pending',
                'message' => 'Sua solicitação já está em análise.',
            ]);
        }

        if ($user->reachedParticipatingPoolsLimit()) {
            return ApiResponse::error($request, 'POOL_LIMIT_REACHED', 'Você já atingiu o limite de 5 bolões.', 409);
        }

        if ($member) {
            $member->update([
                'role' => PoolMemberRole::Member->value,
                'sector' => $sector !== '' ? $sector : null,
                'status' => PoolMemberStatus::Pending->value,
                'activated_by' => null,
                'activated_at' => null,
                'deactivated_by' => null,
                'deactivated_at' => null,
            ]);
        } else {
            $pool->members()->create([
                'user_id' => $user->id,
                'role' => PoolMemberRole::Member->value,
                'sector' => $sector !== '' ? $sector : null,
                'status' => PoolMemberStatus::Pending->value,
            ]);
        }

        return ApiResponse::success($request, [
            'pool_id' => $pool->id,
            'status' => 'pending',
            'message' => 'Solicitação enviada. Aguarde aprovação do gestor.',
        ]);
    }

    public function invite(Request $request, Pool $pool): JsonResponse
    {
        $user = $request->user();

        if (! $this->canManagePool($pool, (int) $user->id, (bool) $user->is_admin)) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado para convidar membros.', 403);
        }

        if ((string) $pool->status !== 'active') {
            return ApiResponse::error($request, 'POOL_UNAVAILABLE', 'Este bolão não está ativo.', 409);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'sector' => ['nullable', 'string', 'max:80'],
        ]);

        $allowedSectors = is_array($pool->sectors) ? array_values($pool->sectors) : [];
        $sector = trim((string) ($validated['sector'] ?? ''));

        if ($sector !== '' && ! in_array($sector, $allowedSectors, true)) {
            return ApiResponse::error($request, 'INVALID_SECTOR', 'Setor inválido para este bolão.', 422, [
                'fields' => ['sector' => ['Setor inválido para este bolão.']],
            ]);
        }

        $invite = PoolInvite::query()->create([
            'pool_id' => $pool->id,
            'invited_by' => $user->id,
            'email' => mb_strtolower(trim((string) $validated['email'])),
            'sector' => $sector !== '' ? $sector : null,
            'token' => Str::random(48),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        return ApiResponse::success($request, [
            'pool_id' => $pool->id,
            'invite' => [
                'id' => $invite->id,
                'email' => $invite->email,
                'sector' => $invite->sector,
                'token' => $invite->token,
                'expires_at' => optional($invite->expires_at)?->toIso8601String(),
            ],
        ], 201);
    }

    public function acceptInvite(Request $request, string $token): JsonResponse
    {
        $invite = PoolInvite::query()
            ->with('pool:id,slug,status')
            ->where('token', $token)
            ->where('status', 'pending')
            ->first();

        if (! $invite || ($invite->expires_at && $invite->expires_at->isPast())) {
            return ApiResponse::error($request, 'INVITE_INVALID_OR_EXPIRED', 'Convite inválido ou expirado.', 404);
        }

        $user = $request->user();

        $member = PoolMember::query()
            ->where('pool_id', $invite->pool_id)
            ->where('user_id', $user->id)
            ->first();

        if (
            ! $member
            && $user->reachedParticipatingPoolsLimit()
        ) {
            return ApiResponse::error($request, 'POOL_LIMIT_REACHED', 'Você já atingiu o limite de 5 bolões.', 409);
        }

        if (! $member) {
            PoolMember::query()->create([
                'pool_id' => $invite->pool_id,
                'user_id' => $user->id,
                'role' => PoolMemberRole::Member->value,
                'sector' => $invite->sector,
                'status' => PoolMemberStatus::Active->value,
                'activated_by' => $invite->invited_by,
                'activated_at' => now(),
            ]);
        } elseif ($member->status !== PoolMemberStatus::Active->value) {
            $member->update([
                'role' => PoolMemberRole::Member->value,
                'sector' => $invite->sector,
                'status' => PoolMemberStatus::Active->value,
                'activated_by' => $invite->invited_by,
                'activated_at' => now(),
                'deactivated_by' => null,
                'deactivated_at' => null,
            ]);
        }

        $invite->update([
            'status' => 'accepted',
            'accepted_by' => $user->id,
            'accepted_at' => now(),
        ]);

        return ApiResponse::success($request, [
            'pool' => [
                'id' => $invite->pool_id,
                'slug' => $invite->pool?->slug,
            ],
            'status' => 'accepted',
        ]);
    }

    public function joinByCode(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'invite_code' => ['required', 'string', 'size:8'],
            'sector' => ['nullable', 'string', 'max:80'],
        ]);

        $code = strtoupper(trim((string) $data['invite_code']));
        $pool = Pool::query()
            ->with('competition:id,code,name')
            ->where('invite_code', $code)
            ->where('status', 'active')
            ->first();

        if (! $pool) {
            return ApiResponse::error($request, 'INVITE_CODE_INVALID', 'Código de convite inválido.', 404);
        }

        $allowedSectors = is_array($pool->sectors) ? array_values($pool->sectors) : [];
        $sector = trim((string) ($data['sector'] ?? ''));
        if (! empty($allowedSectors) && ! in_array($sector, $allowedSectors, true)) {
            return ApiResponse::error($request, 'INVALID_SECTOR', 'Setor inválido para este bolão.', 422, [
                'fields' => ['sector' => ['Setor inválido para este bolão.']],
            ]);
        }

        $member = PoolMember::query()
            ->where('pool_id', $pool->id)
            ->where('user_id', $user->id)
            ->first();

        if ($member && $member->status === PoolMemberStatus::Active->value) {
            return ApiResponse::success($request, [
                'pool_id' => $pool->id,
                'status' => 'already_active',
                'message' => 'Você já participa deste bolão.',
            ]);
        }

        if ($member && $member->status === PoolMemberStatus::Pending->value) {
            return ApiResponse::success($request, [
                'pool_id' => $pool->id,
                'status' => 'already_pending',
                'message' => 'Sua solicitação já está em análise.',
            ]);
        }

        if ($user->reachedParticipatingPoolsLimit()) {
            return ApiResponse::error($request, 'POOL_LIMIT_REACHED', 'Você já atingiu o limite de 5 bolões.', 409);
        }

        if ($member) {
            $member->update([
                'role' => PoolMemberRole::Member->value,
                'sector' => $sector !== '' ? $sector : null,
                'status' => PoolMemberStatus::Pending->value,
                'activated_by' => null,
                'activated_at' => null,
                'deactivated_by' => null,
                'deactivated_at' => null,
            ]);
        } else {
            $pool->members()->create([
                'user_id' => $user->id,
                'role' => PoolMemberRole::Member->value,
                'sector' => $sector !== '' ? $sector : null,
                'status' => PoolMemberStatus::Pending->value,
            ]);
        }

        return ApiResponse::success($request, [
            'pool_id' => $pool->id,
            'pool' => [
                'id' => $pool->id,
                'name' => $pool->name,
                'slug' => $pool->slug,
                'competition' => [
                    'id' => $pool->competition?->id,
                    'code' => $pool->competition?->code,
                    'name' => $pool->competition?->name,
                ],
            ],
            'status' => 'pending',
            'message' => 'Solicitação enviada. Aguarde aprovação do gestor.',
        ]);
    }

    public function leave(Request $request, Pool $pool): JsonResponse
    {
        $user = $request->user();
        $member = PoolMember::query()
            ->where('pool_id', $pool->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $member || ! in_array($member->status, [PoolMemberStatus::Active->value, PoolMemberStatus::Pending->value], true)) {
            return ApiResponse::error($request, 'POOL_MEMBERSHIP_NOT_FOUND', 'Você não participa deste bolão.', 404);
        }

        if ($member->role === PoolMemberRole::Owner->value) {
            return ApiResponse::error($request, 'POOL_OWNER_CANNOT_LEAVE', 'O dono do bolão não pode sair sem transferir a propriedade.', 409);
        }

        $member->update([
            'status' => PoolMemberStatus::Removed->value,
            'deactivated_by' => $user->id,
            'deactivated_at' => now(),
        ]);

        return ApiResponse::success($request, [
            'pool_id' => $pool->id,
            'status' => 'left',
            'message' => 'Você saiu do bolão.',
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
            ->exists();
    }
}
