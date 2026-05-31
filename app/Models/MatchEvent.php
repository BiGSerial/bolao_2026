<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchEvent extends Model
{
    protected $fillable = [
        'football_match_id',
        'provider',
        'provider_event_id',
        'provider_fixture_id',
        'minute',
        'extra_minute',
        'team_id',
        'team_name',
        'player_id',
        'player_name',
        'assist_player_id',
        'assist_name',
        'event_type',
        'event_detail',
        'home_score',
        'away_score',
        'team_goal_number',
        'player_goal_number',
        'raw_payload',
        'fingerprint',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'notified_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'football_match_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(MatchNotification::class);
    }
}
