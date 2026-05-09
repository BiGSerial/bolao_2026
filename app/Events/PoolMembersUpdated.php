<?php

namespace App\Events;

use App\Models\Pool;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PoolMembersUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue;

    public function __construct(public Pool $pool)
    {
        $this->queue = config('queue-priority.broadcast.events', 'broadcast');
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("pool.{$this->pool->id}");
    }

    public function broadcastAs(): string
    {
        return 'MembersUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'pool_id' => $this->pool->id,
            'pending_count' => (int) $this->pool->members()->where('status', 'pending')->count(),
        ];
    }
}

