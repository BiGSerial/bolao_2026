<?php

namespace App\Events;

use App\Models\FootballMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchDetailUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue;

    public function __construct(public FootballMatch $match)
    {
        $this->queue = config('queue-priority.broadcast.events', 'broadcast');
    }

    public function broadcastOn(): Channel
    {
        return new Channel('matches');
    }

    public function broadcastAs(): string
    {
        return 'MatchDetailUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'status' => $this->match->status,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
