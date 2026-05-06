<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Standing extends Model
{
    protected $fillable = [
        'competition_id',
        'competition_season_id',
        'provider',
        'external_id',
        'stage',
        'type',
        'group_name',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return ['raw_payload' => 'array'];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(CompetitionSeason::class, 'competition_season_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(StandingRow::class);
    }
}
