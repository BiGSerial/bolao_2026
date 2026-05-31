<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchNotification extends Model
{
    protected $fillable = [
        'match_event_id',
        'football_match_id',
        'user_id',
        'pool_id',
        'type',
        'channel',
        'title',
        'body',
        'payload',
        'status',
        'sent_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function matchEvent(): BelongsTo
    {
        return $this->belongsTo(MatchEvent::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'football_match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }
}
