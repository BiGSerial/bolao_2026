<?php

namespace App\Services\Notifications;

use App\Models\MatchEvent;
use App\Models\PoolMember;
use App\Models\Prediction;
use App\Models\UserNotificationPreference;

class MatchNotificationAudienceResolver
{
    /**
     * @return array<int>
     */
    public function forGoal(MatchEvent $event): array
    {
        return $this->activeUsersForMatch((int) $event->football_match_id, 'notify_goals');
    }

    /**
     * @return array<int>
     */
    public function forMatchFinished(int $matchId): array
    {
        return $this->activeUsersForMatch($matchId, 'notify_match_finished');
    }

    /**
     * @return array<int>
     */
    public function forMatchSummary(int $matchId): array
    {
        return $this->activeUsersForMatch($matchId, 'notify_match_summary');
    }

    /**
     * @return array<int>
     */
    private function activeUsersForMatch(int $matchId, string $preferenceColumn): array
    {
        $poolIds = Prediction::query()
            ->where('football_match_id', $matchId)
            ->distinct()
            ->pluck('pool_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($poolIds === []) {
            return [];
        }

        $userIds = PoolMember::query()
            ->whereIn('pool_id', $poolIds)
            ->where('status', 'active')
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($userIds === []) {
            return [];
        }

        $disabledIds = UserNotificationPreference::query()
            ->whereIn('user_id', $userIds)
            ->where($preferenceColumn, false)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($disabledIds === []) {
            return $userIds;
        }

        return array_values(array_diff($userIds, $disabledIds));
    }
}
