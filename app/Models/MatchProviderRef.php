<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchProviderRef extends Model
{
    protected $fillable = [
        'football_match_id',
        'provider',
        'external_id',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'football_match_id');
    }
}
