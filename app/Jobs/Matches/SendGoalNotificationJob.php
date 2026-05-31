<?php

namespace App\Jobs\Matches;

use App\Events\Matches\GoalScored;
use App\Models\MatchEvent;
use App\Models\MatchNotification;
use App\Services\Matches\MatchEventGoalClassifier;
use App\Services\Notifications\MatchNotificationAudienceResolver;
use App\Services\Notifications\WebPushService;
use App\Support\Matches\GoalNotificationPayloadBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendGoalNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $matchEventId)
    {
        $this->onQueue(config('queue-priority.broadcast.events', 'broadcast'));
    }

    public function handle(
        GoalNotificationPayloadBuilder $payloadBuilder,
        MatchEventGoalClassifier $goalClassifier,
        MatchNotificationAudienceResolver $audienceResolver,
        WebPushService $webPushService,
    ): void {
        $event = MatchEvent::query()->find($this->matchEventId);
        if (! $event) {
            return;
        }

        if ($event->notified_at) {
            return;
        }

        if (! $goalClassifier->isNotifiableGoal($event->event_type, $event->event_detail)) {
            Log::channel('smart_sync')->info('[notifications] goal_ignored_non_notifiable', [
                'match_event_id' => (int) $event->id,
                'match_id' => (int) $event->football_match_id,
                'event_type' => (string) $event->event_type,
                'event_detail' => (string) $event->event_detail,
            ]);
            return;
        }

        $payload = $payloadBuilder->build($event);
        $userIds = $audienceResolver->forGoal($event);

        broadcast(new GoalScored($payload));

        if ($userIds !== []) {
            $webPushService->sendToUsers($userIds, $payload + [
                'url' => "/pwa/matches/{$event->football_match_id}",
                'tag' => "match-goal-{$event->football_match_id}-{$event->id}",
                'renotify' => true,
            ]);
        }

        foreach ($userIds as $userId) {
            MatchNotification::query()->create([
                'match_event_id' => (int) $event->id,
                'football_match_id' => (int) $event->football_match_id,
                'user_id' => (int) $userId,
                'type' => 'goal',
                'channel' => "users.{$userId}",
                'title' => (string) ($payload['title'] ?? 'Gol'),
                'body' => (string) ($payload['body'] ?? ''),
                'payload' => $payload,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }

        $event->forceFill(['notified_at' => now()])->save();
        Log::channel('smart_sync')->info('[notifications] goal_sent', [
            'match_event_id' => (int) $event->id,
            'match_id' => (int) $event->football_match_id,
            'audience_count' => count($userIds),
        ]);
    }
}
