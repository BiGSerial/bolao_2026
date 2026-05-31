<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PoolChatReadUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $poolId,
        public int $userId,
        public int $lastReadMessageId,
        public ?string $readAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('pool-chat.'.$this->poolId);
    }

    public function broadcastAs(): string
    {
        return 'PoolChatReadUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'pool_id' => $this->poolId,
            'user_id' => $this->userId,
            'last_read_message_id' => $this->lastReadMessageId,
            'read_at' => $this->readAt,
        ];
    }
}
