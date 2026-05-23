<?php

namespace App\Http\Controllers\Api\V1\Rankings;

use App\Http\Controllers\Controller;
use App\Models\Pool;
use App\Models\PoolRanking;
use App\Services\Pools\LivePoolRankingService;
use App\Services\Pools\PoolRankingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoolRankingsController extends Controller
{
    public function __construct(
        private readonly PoolRankingService $poolRankingService,
        private readonly LivePoolRankingService $livePoolRankingService,
    ) {}

    public function index(Request $request, Pool $pool): JsonResponse
    {
        if (! $this->canAccessPool($request, $pool)) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao bolão.', 403);
        }

        $this->poolRankingService->recalculate($pool);

        $rows = PoolRanking::query()
            ->with('user:id,name,display_name')
            ->where('pool_id', $pool->id)
            ->orderBy('position')
            ->orderBy('user_id')
            ->get();

        return ApiResponse::success($request, [
            'pool_id' => $pool->id,
            'items' => $rows->map(fn (PoolRanking $row): array => $this->mapRow($row))->values()->all(),
            'calculated_at' => optional($rows->first()?->last_calculated_at)?->toIso8601String(),
        ]);
    }

    public function live(Request $request, Pool $pool): JsonResponse
    {
        if (! $this->canAccessPool($request, $pool)) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao bolão.', 403);
        }

        $rows = $this->livePoolRankingService->build($pool);

        return ApiResponse::success($request, [
            'pool_id' => $pool->id,
            'items' => $rows->map(fn (object $row): array => [
                'position' => (int) $row->position,
                'user' => [
                    'id' => (int) $row->user_id,
                    'name' => (string) ($row->user?->public_name ?? $row->user?->display_name ?? $row->user?->name ?? '—'),
                ],
                'points_total' => (int) $row->points_total,
                'exact_scores' => (int) $row->exact_scores,
                'correct_results' => (int) $row->correct_results,
                'correct_home_goals' => (int) $row->correct_home_goals,
                'correct_away_goals' => (int) $row->correct_away_goals,
                'predictions_counted' => (int) $row->predictions_counted,
            ])->values()->all(),
            'calculated_at' => now()->toIso8601String(),
        ]);
    }

    private function canAccessPool(Request $request, Pool $pool): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if ((bool) $user->is_admin) {
            return true;
        }

        return $pool->members()->where('user_id', $user->id)->exists();
    }

    private function mapRow(PoolRanking $row): array
    {
        return [
            'position' => (int) $row->position,
            'user' => [
                'id' => (int) $row->user_id,
                'name' => (string) ($row->user?->public_name ?? $row->user?->display_name ?? $row->user?->name ?? '—'),
            ],
            'points_total' => (int) $row->points_total,
            'exact_scores' => (int) $row->exact_scores,
            'correct_results' => (int) $row->correct_results,
            'correct_home_goals' => (int) $row->correct_home_goals,
            'correct_away_goals' => (int) $row->correct_away_goals,
            'predictions_counted' => (int) $row->predictions_counted,
        ];
    }
}
