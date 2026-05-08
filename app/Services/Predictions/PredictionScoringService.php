<?php

namespace App\Services\Predictions;

use App\Models\Pool;
use App\Models\Prediction;

class PredictionScoringService
{
    public function recalculatePool(Pool $pool): void
    {
        Prediction::query()
            ->where('pool_id', $pool->id)
            ->whereHas('footballMatch', fn ($q) => $q
                ->where('status', 'FINISHED')
                ->where('stage', $pool->stage))
            ->chunkById(200, function ($predictions): void {
                $predictions->each(fn (Prediction $prediction) => $this->calculate($prediction));
            });
    }

    public function calculate(Prediction $prediction): void
    {
        $prediction->loadMissing(['pool', 'footballMatch']);

        $pool = $prediction->pool;
        $match = $prediction->footballMatch;

        if ($match->status !== 'FINISHED') {
            return;
        }

        $lockTime = $match->predictionLockTimeFor($pool);
        if ($prediction->last_changed_at && $prediction->last_changed_at->greaterThan($lockTime)) {
            $prediction->update(['eligible' => false, 'ineligible_reason' => 'Palpite alterado apos o limite permitido.', 'points' => 0, 'calculated_at' => now()]);
            return;
        }

        $homeReal = $match->home_score_full_time;
        $awayReal = $match->away_score_full_time;
        if ($homeReal === null || $awayReal === null) {
            return;
        }

        $exact = $prediction->home_score === $homeReal && $prediction->away_score === $awayReal;
        $exactScorePoints = max(0, (int) ($pool->points_exact_score ?? 5));
        $correctResultPoints = max(0, (int) ($pool->points_correct_result ?? 3));
        $correctGoalsPoints = max(0, (int) ($pool->points_correct_goals ?? 1));
        $points = 0;
        if ($exact) {
            $points = $exactScorePoints;
        } else {
            $realResult = $this->resultOf($homeReal, $awayReal);
            $predResult = $this->resultOf($prediction->home_score, $prediction->away_score);
            if ($realResult === $predResult) {
                $points += $correctResultPoints;
            }
            if ($prediction->home_score === $homeReal) {
                $points += $correctGoalsPoints;
            }
            if ($prediction->away_score === $awayReal) {
                $points += $correctGoalsPoints;
            }
        }

        $prediction->update(['eligible' => true, 'ineligible_reason' => null, 'points' => $points, 'calculated_at' => now()]);
    }

    private function resultOf(int $home, int $away): string
    {
        return $home > $away ? 'H' : ($home < $away ? 'A' : 'D');
    }
}
