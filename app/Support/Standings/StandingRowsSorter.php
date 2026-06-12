<?php

namespace App\Support\Standings;

use App\Models\StandingRow;
use Illuminate\Support\Collection;

class StandingRowsSorter
{
    /**
     * @param  Collection<int, StandingRow>  $rows
     * @return Collection<int, StandingRow>
     */
    public static function sort(Collection $rows, string $competitionCode): Collection
    {
        $competitionCode = strtoupper($competitionCode);

        return $rows->sort(function (StandingRow $left, StandingRow $right) use ($competitionCode): int {
            $criteria = $competitionCode === 'BSA'
                ? ['points', 'won', 'goal_difference', 'goals_for']
                : ['points', 'goal_difference', 'goals_for'];

            foreach ($criteria as $field) {
                $comparison = ((int) $right->{$field}) <=> ((int) $left->{$field});
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            $leftPosition = $left->position === null ? PHP_INT_MAX : (int) $left->position;
            $rightPosition = $right->position === null ? PHP_INT_MAX : (int) $right->position;
            $positionComparison = $leftPosition <=> $rightPosition;

            if ($positionComparison !== 0) {
                return $positionComparison;
            }

            $winsComparison = ((int) $right->won) <=> ((int) $left->won);
            if ($winsComparison !== 0) {
                return $winsComparison;
            }

            return strcasecmp(
                (string) ($left->team?->localized_name ?? ''),
                (string) ($right->team?->localized_name ?? ''),
            );
        })->values();
    }

    public static function groupLabel(?string $groupName): string
    {
        $groupName = trim((string) $groupName);

        if ($groupName === '') {
            return 'Classificação Geral';
        }

        if (preg_match('/^(?:GROUP|GRUPO)[ _-]*([A-Z0-9]+)$/i', $groupName, $matches) === 1) {
            return 'Grupo '.strtoupper($matches[1]);
        }

        if (preg_match('/^[A-Z]$/i', $groupName) === 1) {
            return 'Grupo '.strtoupper($groupName);
        }

        return $groupName;
    }
}
