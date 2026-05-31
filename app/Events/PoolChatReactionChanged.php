<?php

namespace App\Events;

use App\Models\PoolChatMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PoolChatReactionChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PoolChatMessage $message)
    {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('pool-chat.'.$this->message->pool_id);
    }

    public function broadcastAs(): string
    {
        return 'PoolChatReactionChanged';
    }

    public function broadcastWith(): array
    {
        $msg = $this->message->loadMissing('reactions');

        return [
            'message_id' => $msg->id,
            'reactions' => $msg->reactions
                ->groupBy('emoji')
                ->map(fn ($group, $emoji) => [
                    'emoji' => $emoji,
                    'count' => $group->count(),
                    'user_ids' => $group->pluck('user_id')->values()->all(),
                ])
                ->values()
                ->all(),
        ];
    }
}
