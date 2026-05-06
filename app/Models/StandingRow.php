<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandingRow extends Model
{
    protected $fillable = [
        'standing_id',
        'team_id',
        'position',
        'played_games',
        'won',
        'draw',
        'lost',
        'goal_difference',
        'goals_for',
        'goals_against',
        'points',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return ['raw_payload' => 'array'];
    }

    public function standing(): BelongsTo
    {
        return $this->belongsTo(Standing::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
