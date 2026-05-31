<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoolChatRead extends Model
{
    protected $fillable = [
        'pool_id',
        'user_id',
        'last_read_message_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(PoolChatMessage::class, 'last_read_message_id');
    }
}
