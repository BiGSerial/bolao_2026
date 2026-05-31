<?php

namespace App\Jobs\Matches;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateMatchSummaryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $matchId)
    {
        $this->onQueue(config('queue-priority.broadcast.events', 'broadcast'));
    }

    public static function dispatchWithDelay(int $matchId): void
    {
        self::dispatch($matchId)->delay(now()->addMinutes(2));
    }

    public function handle(): void
    {
        SendMatchSummaryNotificationJob::dispatch($this->matchId);
    }
}
