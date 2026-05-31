<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoolChatMessageReaction extends Model
{
    protected $fillable = [
        'message_id',
        'user_id',
        'emoji',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(PoolChatMessage::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
