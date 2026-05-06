<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionSeason extends Model
{
    protected $fillable = ['competition_id', 'provider', 'external_id', 'year', 'start_date', 'end_date', 'current_matchday', 'winner_payload'];

    protected function casts(): array
    {
        return ['winner_payload' => 'array', 'start_date' => 'date', 'end_date' => 'date'];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FootballMatch::class);
    }

    public function standings(): HasMany
    {
        return $this->hasMany(Standing::class);
    }
}
