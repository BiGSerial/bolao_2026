<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prediction extends Model
{
    protected $fillable = ['pool_id','user_id','football_match_id','home_score','away_score','points','last_changed_at','locked_at','calculated_at','eligible','ineligible_reason'];

    protected function casts(): array
    {
        return ['last_changed_at' => 'datetime', 'locked_at' => 'datetime', 'calculated_at' => 'datetime', 'eligible' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PredictionVersion::class);
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    public function footballMatch(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
