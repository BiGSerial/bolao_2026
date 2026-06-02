<?php

namespace App\Events;

use App\Models\PoolChatMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PoolChatMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private const EDIT_WINDOW_MINUTES = 15;

    public function __construct(public PoolChatMessage $message)
    {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('pool-chat.'.$this->message->pool_id);
    }

    public function broadcastAs(): string
    {
        return 'PoolChatMessageCreated';
    }

    public function broadcastWith(): array
    {
        $msg = $this->message->loadMissing(['user:id,name,display_name', 'replyTo:id,body,user_id', 'replyTo.user:id,name,display_name', 'reactions.user:id,name,display_name']);

        return [
            'message' => [
                'id' => $msg->id,
                'pool_id' => $msg->pool_id,
                'mentioned_user_ids' => (array) ($msg->mentioned_user_ids ?? []),
                'body' => $msg->deleted_at ? 'Mensagem apagada' : $msg->body,
                'created_at' => optional($msg->created_at)?->toIso8601String(),
                'edited_at' => optional($msg->edited_at)?->toIso8601String(),
                'deleted_at' => optional($msg->deleted_at)?->toIso8601String(),
                'edit_expires_at' => optional($msg->created_at?->copy()->addMinutes(self::EDIT_WINDOW_MINUTES))?->toIso8601String(),
                'can_edit' => ! $msg->deleted_at && $msg->created_at && $msg->created_at->greaterThanOrEqualTo(now()->subMinutes(self::EDIT_WINDOW_MINUTES)),
                'user' => [
                    'id' => $msg->user?->id,
                    'name' => $msg->user?->public_name,
                ],
                'reply_to' => $msg->replyTo ? [
                    'id' => $msg->replyTo->id,
                    'body' => $msg->replyTo->deleted_at ? 'Mensagem apagada' : $msg->replyTo->body,
                    'user_name' => $msg->replyTo->user?->public_name,
                    'deleted_at' => optional($msg->replyTo->deleted_at)?->toIso8601String(),
                ] : null,
                'reactions' => $msg->reactions
                    ->groupBy('emoji')
                    ->map(fn ($group, $emoji) => [
                        'emoji' => $emoji,
                        'count' => $group->count(),
                        'user_ids' => $group->pluck('user_id')->values()->all(),
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }
}
