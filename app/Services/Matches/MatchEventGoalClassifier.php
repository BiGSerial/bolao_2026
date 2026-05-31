<?php

namespace App\Services\Matches;

class MatchEventGoalClassifier
{
    public function isGoalEvent(?string $eventType): bool
    {
        $type = mb_strtolower(trim((string) $eventType));
        if ($type === '') {
            return false;
        }

        return str_contains($type, 'goal');
    }

    public function isDisallowedGoal(?string $eventDetail): bool
    {
        $detail = mb_strtolower(trim((string) $eventDetail));
        if ($detail === '') {
            return false;
        }

        return str_contains($detail, 'disallow')
            || str_contains($detail, 'anulado')
            || str_contains($detail, 'cancelado')
            || str_contains($detail, 'invalid');
    }

    public function isNotifiableGoal(?string $eventType, ?string $eventDetail): bool
    {
        return $this->isGoalEvent($eventType) && ! $this->isDisallowedGoal($eventDetail);
    }
}
