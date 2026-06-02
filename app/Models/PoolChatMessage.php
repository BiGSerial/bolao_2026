<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PoolChatMessage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pool_id',
        'user_id',
        'reply_to_message_id',
        'body',
        'mentioned_user_ids',
        'edited_at',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
            'mentioned_user_ids' => 'array',
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

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id')->withTrashed();
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(PoolChatMessageReaction::class, 'message_id');
    }

    public function edits(): HasMany
    {
        return $this->hasMany(PoolChatMessageEdit::class, 'message_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
