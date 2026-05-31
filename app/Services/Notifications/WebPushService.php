<?php

namespace App\Services\Notifications;

use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushService
{
    /**
     * @param  array<int>  $userIds
     * @param  array<string, mixed>  $payload
     */
    public function sendToUsers(array $userIds, array $payload): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $userIds = array_values(array_filter($userIds, fn (int $id) => $id > 0));

        if ($userIds === [] || ! $this->isEnabled()) {
            return ['queued' => 0, 'sent' => 0, 'failed' => 0, 'deleted' => 0];
        }

        /** @var Collection<int, PushSubscription> $subscriptions */
        $subscriptions = PushSubscription::query()
            ->whereIn('user_id', $userIds)
            ->whereNull('revoked_at')
            ->get();

        if ($subscriptions->isEmpty()) {
            return ['queued' => 0, 'sent' => 0, 'failed' => 0, 'deleted' => 0];
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) config('push-notifications.vapid.subject', ''),
                'publicKey' => (string) config('push-notifications.vapid.public_key', ''),
                'privateKey' => (string) config('push-notifications.vapid.private_key', ''),
            ],
        ]);

        $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $queued = 0;
        foreach ($subscriptions as $subscription) {
            try {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->public_key,
                        'authToken' => $subscription->auth_token,
                        'contentEncoding' => $subscription->content_encoding ?: 'aesgcm',
                    ]),
                    $encodedPayload,
                    [
                        'TTL' => 120,
                        'urgency' => 'high',
                        'topic' => (string) ($payload['tag'] ?? 'bolao-geral'),
                    ]
                );
                $queued++;
            } catch (Throwable $e) {
                Log::warning('webpush.queue_failed', [
                    'subscription_id' => $subscription->id,
                    'endpoint' => $subscription->endpoint,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $sent = 0;
        $failed = 0;
        $deleted = 0;

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()?->getUri()?->__toString();
            if ($report->isSuccess()) {
                $sent++;
                if ($endpoint) {
                    PushSubscription::query()
                        ->where('endpoint', $endpoint)
                        ->update(['last_used_at' => now(), 'revoked_at' => null]);
                }
                continue;
            }

            $failed++;
            if ($endpoint && $report->isSubscriptionExpired()) {
                $deleted += PushSubscription::query()
                    ->where('endpoint', $endpoint)
                    ->update(['revoked_at' => now()]);
            }
        }

        return [
            'queued' => $queued,
            'sent' => $sent,
            'failed' => $failed,
            'deleted' => $deleted,
        ];
    }

    public function isEnabled(): bool
    {
        if (! (bool) config('push-notifications.enabled', false)) {
            return false;
        }

        return (string) config('push-notifications.vapid.public_key', '') !== ''
            && (string) config('push-notifications.vapid.private_key', '') !== ''
            && (string) config('push-notifications.vapid.subject', '') !== '';
    }
}
