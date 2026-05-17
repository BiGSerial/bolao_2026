<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiSyncLog extends Model
{
    protected $fillable = [
        'provider',
        'endpoint',
        'http_status',
        'success',
        'is_request_log',
        'request_method',
        'request_url',
        'request_query',
        'response_payload',
        'request_started_at',
        'request_finished_at',
        'duration_ms',
        'records_total',
        'records_changed',
        'message',
        'meta',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'is_request_log' => 'boolean',
            'request_query' => 'array',
            'response_payload' => 'array',
            'meta' => 'array',
            'request_started_at' => 'datetime',
            'request_finished_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }
}
