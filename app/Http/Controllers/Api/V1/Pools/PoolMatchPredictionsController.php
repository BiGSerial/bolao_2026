<?php

namespace App\Http\Controllers\Api\V1\Pools;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\Prediction;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoolMatchPredictionsController extends Controller
{
    public function __invoke(Request $request, Pool $pool, FootballMatch $match): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->is_admin && !$pool->members()->where('user_id', $user->id)->exists())) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado.', 403);
        }

        $match->load(['homeTeam', 'awayTeam', 'competition']);
        
        $isLocked = $match->isPredictionLockedFor($pool);
        
        $members = $pool->members()
            ->with('user:id,name,display_name')
            ->where('status', 'active')
            ->get();

        $predictions = Prediction::query()
            ->where('pool_id', $pool->id)
            ->where('football_match_id', $match->id)
            ->get()
            ->keyBy('user_id');

        $items = $members->map(function (PoolMember $member) use ($predictions, $isLocked, $user) {
            $prediction = $predictions->get($member->user_id);
            $isOwn = (int) $member->user_id === (int) $user->id;

            return [
                'user' => [
                    'id' => $member->user_id,
                    'name' => $member->user?->public_name ?? $member->user?->display_name ?? $member->user?->name,
                ],
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
