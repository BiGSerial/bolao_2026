<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamProviderRef extends Model
{
    protected $fillable = [
        'team_id',
        'provider',
        'external_id',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
