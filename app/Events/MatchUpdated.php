<?php

namespace App\Events;

use App\Models\FootballMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public FootballMatch $match) {}

    public function broadcastOn(): Channel
    {
        return new Channel('matches');
    }

    public function broadcastAs(): string
    {
        return 'MatchUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->match->id,
            'status' => $this->match->status,
            'home_score_full_time' => $this->match->home_score_full_time,
            'away_score_full_time' => $this->match->away_score_full_time,
            'home_score_half_time' => $this->match->home_score_half_time,
            'away_score_half_time' => $this->match->away_score_half_time,
        ];
    }
}
