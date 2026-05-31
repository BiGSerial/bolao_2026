<?php

namespace App\Support\Matches;

use App\Models\MatchEvent;
use App\Services\Matches\MatchEventGoalClassifier;
use App\Services\Matches\GoalNotificationTextBuilder;

class GoalNotificationPayloadBuilder
{
    public function __construct(
        private readonly GoalNotificationTextBuilder $textBuilder,
        private readonly MatchEventGoalClassifier $goalClassifier,
    ) {
    }

    public function build(MatchEvent $event): array
    {
        $event->loadMissing('match.homeTeam:id,crest,name', 'match.awayTeam:id,crest,name');

        $isDisallowed = $this->goalClassifier->isDisallowedGoal($event->event_detail);
        $teamLogo = $this->resolveTeamLogo($event);

        $text = $this->textBuilder->build([
            'team_name' => $event->team_name,
            'player_name' => $event->player_name,
            'assist_name' => $event->assist_name,
            'event_detail' => $event->event_detail,
            'minute' => $event->minute,
            'extra_minute' => $event->extra_minute,
            'team_goal_number' => $event->team_goal_number,
            'player_goal_number' => $event->player_goal_number,
            'home_team_name' => $event->match?->homeTeam?->localized_name ?: $event->match?->homeTeam?->name,
            'away_team_name' => $event->match?->awayTeam?->localized_name ?: $event->match?->awayTeam?->name,
            'home_score' => $event->home_score,
            'away_score' => $event->away_score,
            'is_disallowed' => $isDisallowed,
        ]);

        return [
            'type' => 'goal',
            'match_id' => (int) $event->football_match_id,
            'event_id' => (int) $event->id,
            'team' => [
                'id' => $event->team_id,
                'name' => $event->team_name,
                'logo' => $teamLogo,
            ],
            'player' => [
                'id' => $event->player_id,
                'name' => $event->player_name,
            ],
            'assist' => [
                'id' => $event->assist_player_id,
                'name' => $event->assist_name,
            ],
            'minute' => $event->minute,
            'extra_minute' => $event->extra_minute,
            'team_goal_number' => $event->team_goal_number,
            'player_goal_number' => $event->player_goal_number,
            'score' => [
                'home' => $event->home_score,
                'away' => $event->away_score,
            ],
            'is_disallowed' => $isDisallowed,
            'title' => $text['title'],
            'body' => $text['body'],
            'sent_at' => now()->toIso8601String(),
        ];
    }

    private function resolveTeamLogo(MatchEvent $event): ?string
    {
        $match = $event->match;
        if (! $match) {
            return null;
        }

        $home = $match->homeTeam;
        $away = $match->awayTeam;

        if ($event->team_name && $home && strcasecmp((string) $event->team_name, (string) $home->name) === 0) {
            return $home->crest ?: null;
        }

        if ($event->team_name && $away && strcasecmp((string) $event->team_name, (string) $away->name) === 0) {
            return $away->crest ?: null;
        }

        return $home?->crest ?: $away?->crest ?: null;
    }
}
