<?php

namespace App\Services\Pools;

use App\Events\PoolRankingUpdated;
use App\Models\Pool;
use App\Models\PoolRanking;
use App\Models\User;
use App\Notifications\PoolFinalizedNotification;
use App\Services\Predictions\PredictionScoringService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class PoolFinalizationService
{
    public function __construct(
        private readonly PredictionScoringService $scoringService,
        private readonly PoolRankingService $rankingService,
    ) {
    }

    public function finalize(Pool $pool): array
    {
        DB::transaction(function () use ($pool): void {
            $this->scoringService->recalculatePool($pool);
            $this->rankingService->recalculate($pool);
            $pool->update(['status' => 'archived']);
        });

        $pool->refresh();
        $rankings = PoolRanking::query()
            ->where('pool_id', $pool->id)
            ->with('user:id,name,display_name,email')
            ->orderBy('position')
            ->orderByDesc('points_total')
            ->get();

        $stats = [
            'exact_scores' => (int) $rankings->sum('exact_scores'),
            'correct_results' => (int) $rankings->sum('correct_results'),
            'correct_home_goals' => (int) $rankings->sum('correct_home_goals'),
            'correct_away_goals' => (int) $rankings->sum('correct_away_goals'),
            'predictions_counted' => (int) $rankings->sum('predictions_counted'),
        ];

        $users = User::query()
            ->whereIn('id', $pool->members()->where('status', 'active')->pluck('user_id'))
            ->whereNotNull('email')
            ->get();

        if ($users->isNotEmpty()) {
            Notification::send($users, new PoolFinalizedNotification($pool, $rankings, $stats));
        }

        PoolRankingUpdated::dispatch($pool);

        return [
            'pool' => $pool,
            'rankings' => $rankings,
            'stats' => $stats,
            'notified_users' => $users->count(),
        ];
    }
}
