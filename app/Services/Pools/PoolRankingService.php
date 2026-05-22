<?php

namespace App\Services\Pools;

use App\Models\Pool;
use Illuminate\Support\Facades\DB;

class PoolRankingService
{
    private const DEFAULT_TIE_BREAKERS = [
        'exact_scores',
        'correct_results',
    ];

    private const ALLOWED_TIE_BREAKERS = [
        'exact_scores',
        'correct_results',
        'correct_home_goals',
        'correct_away_goals',
        'predictions_counted',
    ];

    public function recalculate(Pool $pool): void
    {
        $rowsQuery = DB::table('predictions')
            ->join('football_matches', 'predictions.football_match_id', '=', 'football_matches.id')
            ->join('pool_members', function ($join): void {
                $join->on('predictions.pool_id', '=', 'pool_members.pool_id')
                    ->on('predictions.user_id', '=', 'pool_members.user_id');
            })
            ->where('predictions.pool_id', $pool->id)
            ->where('predictions.eligible', true)
            ->where('football_matches.status', 'FINISHED')
            ->where('football_matches.stage', $pool->stage)
            ->where('pool_members.status', 'active')
            ->selectRaw('
                predictions.user_id,
                SUM(predictions.points) as points_total,
                SUM(CASE WHEN predictions.home_score = football_matches.home_score_full_time AND predictions.away_score = football_matches.away_score_full_time THEN 1 ELSE 0 END) as exact_scores,
                SUM(CASE
                    WHEN (football_matches.home_score_full_time > football_matches.away_score_full_time AND predictions.home_score > predictions.away_score)
                      OR (football_matches.home_score_full_time < football_matches.away_score_full_time AND predictions.home_score < predictions.away_score)
                      OR (football_matches.home_score_full_time = football_matches.away_score_full_time AND predictions.home_score = predictions.away_score)
                    THEN 1 ELSE 0 END) as correct_results,
                SUM(CASE WHEN predictions.home_score = football_matches.home_score_full_time THEN 1 ELSE 0 END) as correct_home_goals,
                SUM(CASE WHEN predictions.away_score = football_matches.away_score_full_time THEN 1 ELSE 0 END) as correct_away_goals,
                COUNT(*) as predictions_counted
            ')
            ->groupBy('predictions.user_id')
            ->orderByDesc('points_total');

        foreach ($this->resolveTieBreakers($pool->tie_breakers ?? null, $pool) as $criterion) {
            $rowsQuery->orderByDesc($criterion);
        }

        $rows = $rowsQuery->orderBy('predictions.user_id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $position = 1;
        $index = 0;
        $previousKey = null;
        $activeTieBreakers = $this->resolveTieBreakers($pool->tie_breakers ?? null, $pool);

        $rankingRows = [];

        foreach ($rows as $row) {
            $index++;
            $currentKey = [
                (int) $row->points_total,
            ];
            foreach ($activeTieBreakers as $criterion) {
                $currentKey[] = (int) ($row->{$criterion} ?? 0);
            }

            if ($previousKey !== null && $currentKey !== $previousKey) {
                $position = $index;
            }

            $rankingRows[] = [
                'pool_id' => $pool->id,
                'user_id' => $row->user_id,
                'points_total' => $row->points_total,
                'exact_scores' => $row->exact_scores,
                'correct_results' => $row->correct_results,
                'correct_home_goals' => $row->correct_home_goals,
                'correct_away_goals' => $row->correct_away_goals,
                'predictions_counted' => $row->predictions_counted,
                'position' => $position,
                'last_calculated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $previousKey = $currentKey;
        }

        DB::transaction(function () use ($pool, $rankingRows): void {
            DB::table('pool_rankings')->where('pool_id', $pool->id)->delete();
            DB::table('pool_rankings')->insert($rankingRows);
        });
    }

    /**
     * @param  mixed  $raw
     * @return string[]
     */
    private function resolveTieBreakers(mixed $raw, Pool $pool): array
    {
        $allowedForPool = $this->allowedTieBreakersForPool($pool);

        if (! is_array($raw) || empty($raw)) {
            return array_values(array_filter(
                self::DEFAULT_TIE_BREAKERS,
                fn (string $item) => in_array($item, $allowedForPool, true)
            ));
        }

        $valid = [];
        foreach ($raw as $item) {
            $criterion = (string) $item;
            if (
                in_array($criterion, self::ALLOWED_TIE_BREAKERS, true) &&
                in_array($criterion, $allowedForPool, true) &&
                ! in_array($criterion, $valid, true)
            ) {
                $valid[] = $criterion;
            }
        }

        if (! empty($valid)) {
            return $valid;
        }

        return array_values(array_filter(
            self::DEFAULT_TIE_BREAKERS,
            fn (string $item) => in_array($item, $allowedForPool, true)
        ));
    }

    /**
     * @return string[]
     */
    private function allowedTieBreakersForPool(Pool $pool): array
    {
        $allowed = ['predictions_counted'];

        if ((int) ($pool->points_exact_score ?? 5) > 0) {
            $allowed[] = 'exact_scores';
        }

        if ((int) ($pool->points_correct_result ?? 3) > 0) {
            $allowed[] = 'correct_results';
        }

        if ((int) ($pool->points_correct_goals ?? 1) > 0) {
            $allowed[] = 'correct_home_goals';
            $allowed[] = 'correct_away_goals';
        }

        return $allowed;
    }
}
