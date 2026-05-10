<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    protected $fillable = ['provider', 'external_id', 'code', 'name', 'type', 'emblem'];

    public function seasons(): HasMany
    {
        return $this->hasMany(CompetitionSeason::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FootballMatch::class);
    }

    public function standings(): HasMany
    {
        return $this->hasMany(Standing::class);
    }

    public function pools(): HasMany
    {
        return $this->hasMany(Pool::class);
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(CompetitionPackage::class, 'competition_package_items')
            ->withPivot('competition_code')
            ->withTimestamps();
    }
}
