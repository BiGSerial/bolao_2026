<?php

namespace App\Services\Pools;

use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\Prediction;
use Illuminate\Support\Collection;

class LivePoolRankingService
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

    public function build(Pool $pool): Collection
    {
        $members = PoolMember::query()
            ->where('pool_id', (int) $pool->id)
            ->where('status', 'active')
            ->with('user:id,name,display_name')
            ->get();

        if ($members->isEmpty()) {
            return collect();
        }

        $rows = [];
        foreach ($members as $member) {
            $rows[(int) $member->user_id] = (object) [
                'user_id' => (int) $member->user_id,
                'user' => $member->user,
                'position' => 1,
                'points_total' => 0,
                'exact_scores' => 0,
                'correct_results' => 0,
                'correct_home_goals' => 0,
                'correct_away_goals' => 0,
                'predictions_counted' => 0,
            ];
        }

        $predictions = Prediction::query()
            ->where('pool_id', (int) $pool->id)
            ->where('eligible', true)
            ->whereIn('user_id', array_keys($rows))
            ->with('footballMatch:id,status,stage,home_score_full_time,away_score_full_time')
            ->get();

        foreach ($predictions as $prediction) {
            $match = $prediction->footballMatch;
            if (! $match) {
                continue;
            }

            if ($pool->stage && $match->stage && $match->stage !== $pool->stage) {
                continue;
            }

            $homeReal = is_numeric($match->home_score_full_time) ? (int) $match->home_score_full_time : null;
            $awayReal = is_numeric($match->away_score_full_time) ? (int) $match->away_score_full_time : null;
            if ($homeReal === null || $awayReal === null) {
                continue;
            }

            $row = $rows[(int) $prediction->user_id] ?? null;
            if (! $row) {
                continue;
            }

            $predictionHome = (int) $prediction->home_score;
            $predictionAway = (int) $prediction->away_score;
            $row->predictions_counted++;

            $isExact = $predictionHome === $homeReal && $predictionAway === $awayReal;
            if ($isExact) {
                $row->exact_scores++;
            }

            $hasCorrectResult = $this->resultOf($homeReal, $awayReal) === $this->resultOf($predictionHome, $predictionAway);
            if ($hasCorrectResult) {
                $row->correct_results++;
            }

            if (! $isExact) {
                $hitHomeGoals = $predictionHome === $homeReal;
                $hitAwayGoals = $predictionAway === $awayReal;
                $correctGoalsMode = (string) ($pool->correct_goals_mode ?? 'both_teams');

                if ($correctGoalsMode === 'winner_only') {
                    if ($homeReal > $awayReal && $hitHomeGoals) {
                        $row->correct_home_goals++;
                    } elseif ($awayReal > $homeReal && $hitAwayGoals) {
                        $row->correct_away_goals++;
                    }
                } else {
                    if ($hitHomeGoals) {
                        $row->correct_home_goals++;
                    } elseif ($hitAwayGoals) {
                        $row->correct_away_goals++;
                    }
                }
            }

            $row->points_total += (int) ($this->calculatePerMatchPoints(
                pool: $pool,
                predictionHome: $predictionHome,
                predictionAway: $predictionAway,
                homeReal: $homeReal,
                awayReal: $awayReal,
            ) ?? 0);
        }

        $rowsList = array_values($rows);
        $tieBreakers = $this->resolveTieBreakers($pool->tie_breakers ?? null, $pool);

        usort($rowsList, function (object $left, object $right) use ($tieBreakers): int {
            $cmp = ((int) $right->points_total) <=> ((int) $left->points_total);
            if ($cmp !== 0) {
                return $cmp;
            }

            foreach ($tieBreakers as $criterion) {
                $cmp = ((int) ($right->{$criterion} ?? 0)) <=> ((int) ($left->{$criterion} ?? 0));
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            $leftName = mb_strtolower((string) ($left->user?->public_name ?? $left->user?->display_name ?? $left->user?->name ?? ''));
            $rightName = mb_strtolower((string) ($right->user?->public_name ?? $right->user?->display_name ?? $right->user?->name ?? ''));
            $cmp = $leftName <=> $rightName;
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) $left->user_id) <=> ((int) $right->user_id);
        });

        $position = 1;
        $previousKey = null;
        foreach ($rowsList as $index => $row) {
            $currentKey = [(int) $row->points_total];
            foreach ($tieBreakers as $criterion) {
                $currentKey[] = (int) ($row->{$criterion} ?? 0);
            }

            if ($previousKey !== null && $currentKey !== $previousKey) {
                $position = $index + 1;
            }

            $row->position = $position;
            $previousKey = $currentKey;
        }

        return collect($rowsList)->values();
    }

    private function calculatePerMatchPoints(
        Pool $pool,
        int $predictionHome,
        int $predictionAway,
        ?int $homeReal,
        ?int $awayReal,
    ): ?int {
        if ($homeReal === null || $awayReal === null) {
            return null;
        }

        $exactScorePoints = max(0, (int) ($pool->points_exact_score ?? 5));
        $correctResultPoints = max(0, (int) ($pool->points_correct_result ?? 3));
        $correctGoalsPoints = max(0, (int) ($pool->points_correct_goals ?? 1));

        $isExact = $predictionHome === $homeReal && $predictionAway === $awayReal;
        if ($isExact) {
            return $exactScorePoints;
        }

        $points = 0;
        if ($this->resultOf($homeReal, $awayReal) === $this->resultOf($predictionHome, $predictionAway)) {
            $points += $correctResultPoints;
        }

        $hitHomeGoals = $predictionHome === $homeReal;
        $hitAwayGoals = $predictionAway === $awayReal;
        $correctGoalsMode = (string) ($pool->correct_goals_mode ?? 'both_teams');

        if ($correctGoalsMode === 'winner_only') {
            if ($homeReal > $awayReal && $hitHomeGoals) {
                $points += $correctGoalsPoints;
            } elseif ($awayReal > $homeReal && $hitAwayGoals) {
                $points += $correctGoalsPoints;
            }
        } else {
            if ($hitHomeGoals || $hitAwayGoals) {
                $points += $correctGoalsPoints;
            }
        }

        return $points;
    }

    private function resultOf(int $home, int $away): string
    {
        return $home > $away ? 'H' : ($home < $away ? 'A' : 'D');
    }

    /**
     * @param mixed $raw
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

        if ($valid !== []) {
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
