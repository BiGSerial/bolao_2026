<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pool extends Model
{
    protected $fillable = [
        'owner_id','name','slug','description','instructions','sectors','tie_breakers','visibility','status','invite_code',
        'allow_prediction_changes','prediction_lock_minutes','allow_pending_member_predictions','stage',
    ];

    protected function casts(): array
    {
        return [
            'allow_prediction_changes' => 'boolean',
            'allow_pending_member_predictions' => 'boolean',
            'sectors' => 'array',
            'tie_breakers' => 'array',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(PoolMember::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activeMembers(): HasMany
    {
        return $this->hasMany(PoolMember::class)->where('status', 'active');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    public function rankings(): HasMany
    {
        return $this->hasMany(PoolRanking::class);
    }

    public function predictionLockMinutes(): int
    {
        return max(10, (int) $this->prediction_lock_minutes);
    }
}
