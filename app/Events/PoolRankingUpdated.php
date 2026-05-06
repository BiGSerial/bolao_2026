<?php

namespace App\Events;

use App\Models\Pool;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PoolRankingUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Pool $pool) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("pool.{$this->pool->id}");
    }

    public function broadcastAs(): string
    {
        return 'RankingUpdated';
    }

    public function broadcastWith(): array
    {
        return ['pool_id' => $this->pool->id];
    }
}
