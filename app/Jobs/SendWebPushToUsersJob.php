<?php

namespace App\Jobs;

use App\Services\Notifications\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class SendWebPushToUsersJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int>  $userIds
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $userIds,
        public array $payload,
        public ?string $dedupeKey = null,
        public int $dedupeSeconds = 60,
    ) {
    }

    public function handle(WebPushService $webPushService): void
    {
        if ($this->dedupeKey) {
            $ok = Cache::add('push:dedupe:'.$this->dedupeKey, 1, now()->addSeconds(max(5, $this->dedupeSeconds)));
            if (! $ok) {
                return;
            }
        }

        $webPushService->sendToUsers($this->userIds, $this->payload);
    }
}

