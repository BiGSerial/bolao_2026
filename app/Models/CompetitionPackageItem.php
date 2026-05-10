<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionPackageItem extends Model
{
    protected $fillable = ['competition_package_id', 'competition_id', 'competition_code'];

    public function package(): BelongsTo
    {
        return $this->belongsTo(CompetitionPackage::class, 'competition_package_id');
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }
}
