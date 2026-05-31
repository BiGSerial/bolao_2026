<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PoolChatTypingChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $poolId,
        public int $userId,
        public string $userName,
        public bool $typing,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('pool-chat.'.$this->poolId);
    }

    public function broadcastAs(): string
    {
        return 'PoolChatTypingChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'pool_id' => $this->poolId,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'typing' => $this->typing,
        ];
    }
}
