<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiSyncLog extends Model
{
    protected $fillable = ['provider','endpoint','http_status','success','records_total','records_changed','message','meta','synced_at'];

    protected function casts(): array
    {
        return ['success' => 'boolean', 'meta' => 'array', 'synced_at' => 'datetime'];
    }
}
