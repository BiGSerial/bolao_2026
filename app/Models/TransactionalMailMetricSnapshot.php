<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionalMailMetricSnapshot extends Model
{
    protected $fillable = [
        'provider',
        'reference_date',
        'period_type',
        'messages',
        'bounces',
        'hard_bounces',
        'openings',
        'total_hired',
        'total_excess_hired',
        'total_consumed',
        'total_exceeded',
        'total_available',
        'payload',
        'synced_at',
    ];

    protected $casts = [
        'reference_date' => 'date',
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];
}
