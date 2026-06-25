<?php

namespace App\Http\Controllers\Api\V1\Pools;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\Prediction;
use App\Services\Pools\LivePoolRankingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoolMatchPredictionsController extends Controller
{
    public function __construct(
        private readonly LivePoolRankingService $livePoolRankingService,
    ) {}

    public function __invoke(Request $request, Pool $pool, FootballMatch $match): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->is_admin && !$pool->members()->where('user_id', $user->id)->exists())) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado.', 403);
        }

        $match->load(['homeTeam', 'awayTeam', 'competition']);

        $isLocked = $match->isPredictionLockedFor($pool);

        $predictions = Prediction::query()
            ->where('pool_id', $pool->id)
            ->where('football_match_id', $match->id)
            ->get()
            ->keyBy('user_id');

        $rankingRows = $this->livePoolRankingService->build($pool)->values();

        $items = $rankingRows->map(function (object $row) use ($predictions, $isLocked, $user) {
            $userId = (int) ($row->user_id ?? 0);
            $prediction = $predictions->get($userId);
            $isOwn = $userId === (int) $user->id;

            return [
                'position' => (int) ($row->position ?? 0),
                'user' => [
                    'id' => $userId,
                    'name' => $row->user?->public_name ?? $row->user?->display_name ?? $row->user?->name,
                ],
                'points_total' => (int) ($row->points_total ?? 0),
                'exact_scores' => (int) ($row->exact_scores ?? 0),
                'correct_results' => (int) ($row->correct_results ?? 0),
                'correct_home_goals' => (int) ($row->correct_home_goals ?? 0),
                'correct_away_goals' => (int) ($row->correct_away_goals ?? 0),
                'predictions_counted' => (int) ($row->predictions_counted ?? 0),
                'prediction' => ($isLocked || $isOwn) ? ($prediction ? [
                    'home_score' => $prediction->home_score,
                    'away_score' => $prediction->away_score,
                ] : null) : 'hidden',
            ];
        });

        return ApiResponse::success($request, [
            'match' => [
                'id' => $match->id,
                'status' => $match->status,
                'local_date' => $match->kickoffAtBrazil()?->toIso8601String(),
                'home_team' => [
                    'name' => $match->homeTeam?->localized_name,
                    'crest' => $match->homeTeam?->crest,
                ],
                'away_team' => [
                    'name' => $match->awayTeam?->localized_name,
                    'crest' => $match->awayTeam?->crest,
                ],
                'score' => [
                    'home' => $match->home_score_full_time,
                    'away' => $match->away_score_full_time,
                ],
            ],
            'is_locked' => $isLocked,
            'predictions' => $items,
        ]);
    }
}
