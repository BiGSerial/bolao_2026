<?php

namespace App\Http\Controllers\Api\V1\Pools;

use App\Enums\PoolMemberStatus;
use App\Http\Controllers\Controller;
use App\Models\Pool;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoolsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $memberPools = Pool::query()
            ->with(['competition:id,code,name'])
            ->whereHas('members', function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->whereIn('status', [PoolMemberStatus::Active->value, PoolMemberStatus::Pending->value]);
            })
            ->orderBy('name')
            ->get();

        $discoverablePools = Pool::query()
            ->with(['competition:id,code,name'])
            ->where('visibility', 'public')
            ->where('status', 'active')
            ->whereDoesntHave('members', function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return ApiResponse::success($request, [
            'member_pools' => $memberPools->map(fn (Pool $pool): array => $this->poolSummary($pool, $user->id))->values()->all(),
            'discoverable_pools' => $discoverablePools->map(fn (Pool $pool): array => $this->poolSummary($pool, $user->id))->values()->all(),
        ]);
    }

    public function show(Request $request, Pool $pool): JsonResponse
    {
        $user = $request->user();

        $membership = $pool->members()
            ->where('user_id', $user->id)
            ->first(['id', 'role', 'status', 'sector']);

        $isPublic = (string) $pool->visibility === 'public' && (string) $pool->status === 'active';

        if (! $membership && ! $isPublic) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao bolão.', 403);
        }

        $pool->loadMissing(['competition:id,code,name', 'owner:id,name,display_name']);

        return ApiResponse::success($request, [
            'id' => $pool->id,
            'name' => $pool->name,
            'slug' => $pool->slug,
            'description' => $pool->description,
            'visibility' => $pool->visibility,
            'status' => $pool->status,
            'stage' => $pool->stage,
            'allow_prediction_changes' => (bool) $pool->allow_prediction_changes,
            'allow_pending_member_predictions' => (bool) $pool->allow_pending_member_predictions,
            'prediction_lock_minutes' => (int) $pool->prediction_lock_minutes,
            'scoring' => [
                'points_exact_score' => (int) ($pool->points_exact_score ?? 0),
                'points_correct_result' => (int) ($pool->points_correct_result ?? 0),
                'points_correct_goals' => (int) ($pool->points_correct_goals ?? 0),
                'correct_goals_mode' => (string) ($pool->correct_goals_mode ?? 'both_teams'),
            ],
            'competition' => [
                'id' => $pool->competition?->id,
                'code' => $pool->competition?->code,
                'name' => $pool->competition?->name,
            ],
            'owner' => [
                'id' => $pool->owner?->id,
                'name' => $pool->owner?->public_name,
            ],
            'membership' => $membership ? [
                'role' => $membership->role,
                'status' => $membership->status,
                'sector' => $membership->sector,
            ] : null,
        ]);
    }

    private function poolSummary(Pool $pool, int $userId): array
    {
        $membership = $pool->members()
            ->where('user_id', $userId)
            ->first(['role', 'status']);

        return [
            'id' => $pool->id,
            'name' => $pool->name,
            'slug' => $pool->slug,
            'visibility' => $pool->visibility,
            'status' => $pool->status,
            'competition' => [
                'id' => $pool->competition?->id,
                'code' => $pool->competition?->code,
                'name' => $pool->competition?->name,
            ],
            'membership' => $membership ? [
                'role' => $membership->role,
                'status' => $membership->status,
            ] : null,
        ];
    }
}
