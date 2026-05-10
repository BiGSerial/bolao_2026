<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionPackage extends Model
{
    protected $fillable = ['code', 'name', 'active', 'description'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CompetitionPackageItem::class);
    }

    public function competitions(): BelongsToMany
    {
        return $this->belongsToMany(Competition::class, 'competition_package_items')
            ->withPivot('competition_code')
            ->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
