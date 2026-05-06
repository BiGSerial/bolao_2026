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
        DB::table('pool_rankings')->where('pool_id', $pool->id)->delete();

        $rows = DB::table('predictions')
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
                SUM(CASE WHEN predictions.home_score = football_matches.home_score AND predictions.away_score = football_matches.away_score THEN 1 ELSE 0 END) as exact_scores,
                SUM(CASE
                    WHEN (football_matches.home_score > football_matches.away_score AND predictions.home_score > predictions.away_score)
                      OR (football_matches.home_score < football_matches.away_score AND predictions.home_score < predictions.away_score)
                      OR (football_matches.home_score = football_matches.away_score AND predictions.home_score = predictions.away_score)
                    THEN 1 ELSE 0 END) as correct_results,
                SUM(CASE WHEN predictions.home_score = football_matches.home_score THEN 1 ELSE 0 END) as correct_home_goals,
                SUM(CASE WHEN predictions.away_score = football_matches.away_score THEN 1 ELSE 0 END) as correct_away_goals,
                COUNT(*) as predictions_counted
            ')
            ->groupBy('predictions.user_id')
            ->orderByDesc('points_total');

        foreach ($this->resolveTieBreakers($pool->tie_breakers ?? null) as $criterion) {
            $rows->orderByDesc($criterion);
        }

        $rows = $rows->orderBy('predictions.user_id')
            ->get();

        $position = 1;
        foreach ($rows as $row) {
            DB::table('pool_rankings')->insert([
                'pool_id' => $pool->id,
                'user_id' => $row->user_id,
                'points_total' => $row->points_total,
                'exact_scores' => $row->exact_scores,
                'correct_results' => $row->correct_results,
                'correct_home_goals' => $row->correct_home_goals,
                'correct_away_goals' => $row->correct_away_goals,
                'predictions_counted' => $row->predictions_counted,
                'position' => $position++,
                'last_calculated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  mixed  $raw
     * @return string[]
     */
    private function resolveTieBreakers(mixed $raw): array
    {
        if (! is_array($raw) || empty($raw)) {
            return self::DEFAULT_TIE_BREAKERS;
        }

        $valid = [];
        foreach ($raw as $item) {
            $criterion = (string) $item;
            if (in_array($criterion, self::ALLOWED_TIE_BREAKERS, true) && ! in_array($criterion, $valid, true)) {
                $valid[] = $criterion;
            }
        }

        return ! empty($valid) ? $valid : self::DEFAULT_TIE_BREAKERS;
    }
}
