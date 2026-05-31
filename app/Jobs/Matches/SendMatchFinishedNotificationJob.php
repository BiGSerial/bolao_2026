<?php

namespace App\Jobs\Matches;

use App\Events\Matches\MatchFinished;
use App\Models\FootballMatch;
use App\Models\MatchNotification;
use App\Services\Notifications\MatchNotificationAudienceResolver;
use App\Services\Notifications\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendMatchFinishedNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $matchId)
    {
        $this->onQueue(config('queue-priority.broadcast.events', 'broadcast'));
    }

    public function handle(
        MatchNotificationAudienceResolver $audienceResolver,
        WebPushService $webPushService,
    ): void {
        $match = FootballMatch::query()->with(['homeTeam:id,name,short_name', 'awayTeam:id,name,short_name'])->find($this->matchId);
        if (! $match) {
            return;
        }

        $home = (string) ($match->homeTeam?->short_name ?: $match->homeTeam?->name ?: 'Casa');
        $away = (string) ($match->awayTeam?->short_name ?: $match->awayTeam?->name ?: 'Visitante');

        $payload = [
            'type' => 'match_finished',
            'match_id' => (int) $match->id,
            'title' => 'Fim de jogo',
            'body' => "{$home} {$match->home_score_full_time} x {$match->away_score_full_time} {$away}.",
            'score' => [
                'home' => $match->home_score_full_time,
                'away' => $match->away_score_full_time,
            ],
            'sent_at' => now()->toIso8601String(),
        ];

        broadcast(new MatchFinished($payload));

        $userIds = $audienceResolver->forMatchFinished((int) $match->id);
        if ($userIds !== []) {
            $webPushService->sendToUsers($userIds, $payload + [
                'url' => "/pwa/matches/{$match->id}",
                'tag' => "match-finished-{$match->id}",
                'renotify' => true,
            ]);
        }

        foreach ($userIds as $userId) {
            MatchNotification::query()->create([
                'football_match_id' => (int) $match->id,
                'user_id' => (int) $userId,
                'type' => 'match_finished',
                'channel' => "users.{$userId}",
                'title' => (string) $payload['title'],
                'body' => (string) $payload['body'],
                'payload' => $payload,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }

        Log::channel('smart_sync')->info('[notifications] match_finished_sent', [
            'match_id' => (int) $match->id,
            'audience_count' => count($userIds),
        ]);
    }
}
