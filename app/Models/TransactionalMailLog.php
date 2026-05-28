<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionalMailLog extends Model
{
    protected $fillable = [
        'provider',
        'from_address',
        'to_address',
        'subject',
        'status',
        'external_id',
        'attempts',
        'last_http_status',
        'payload',
        'response',
        'last_error',
        'queued_at',
        'sending_at',
        'sent_at',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'queued_at' => 'datetime',
        'sending_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}

