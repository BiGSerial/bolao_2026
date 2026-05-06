<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoolRanking extends Model
{
    protected $table = 'pool_rankings';

    protected $fillable = [
        'pool_id',
        'user_id',
        'points_total',
        'exact_scores',
        'correct_results',
        'correct_home_goals',
        'correct_away_goals',
        'predictions_counted',
        'position',
        'last_calculated_at',
    ];

    protected function casts(): array
    {
        return ['last_calculated_at' => 'datetime'];
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
