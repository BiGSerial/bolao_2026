<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FootballMatchDetail extends Model
{
    protected $fillable = [
        'football_match_id',
        'provider',
        'external_id',
        'payload',
        'fetched_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'football_match_id');
    }
}

