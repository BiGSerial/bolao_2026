<?php

namespace App\Events\Matches;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GoalScored implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue;

    public function __construct(public array $payload)
    {
        $this->queue = config('queue-priority.broadcast.events', 'broadcast');
    }

    public function broadcastOn(): array
    {
        $matchId = (int) ($this->payload['match_id'] ?? 0);

        return [new PrivateChannel("matches.{$matchId}")];
    }

    public function broadcastAs(): string
    {
        return 'goal.scored';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
