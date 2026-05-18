<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PoolJoinRequestCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue;

    public function __construct(
        public int $managerUserId,
        public int $poolId,
        public int $pendingCount
    ) {
        $this->queue = config('queue-priority.broadcast.events', 'broadcast');
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("App.Models.User.{$this->managerUserId}");
    }

    public function broadcastAs(): string
    {
        return 'PoolJoinRequestCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'pool_id' => $this->poolId,
            'pending_count' => $this->pendingCount,
        ];
    }
}

