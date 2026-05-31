<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'notify_goals',
        'notify_match_start',
        'notify_match_finished',
        'notify_match_summary',
        'notify_pool_ranking',
        'notify_only_my_predictions',
        'notify_sound',
    ];

    protected function casts(): array
    {
        return [
            'notify_goals' => 'boolean',
            'notify_match_start' => 'boolean',
            'notify_match_finished' => 'boolean',
            'notify_match_summary' => 'boolean',
            'notify_pool_ranking' => 'boolean',
            'notify_only_my_predictions' => 'boolean',
            'notify_sound' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
