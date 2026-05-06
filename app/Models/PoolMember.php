<?php

namespace App\Models;

use App\Enums\PoolMemberStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoolMember extends Model
{
    protected $fillable = ['pool_id','user_id','role','sector','status','activated_by','activated_at','deactivated_by','deactivated_at','admin_note'];

    protected function casts(): array
    {
        return ['activated_at' => 'datetime', 'deactivated_at' => 'datetime'];
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activate(User $actor): void
    {
        $this->update([
            'status' => PoolMemberStatus::Active->value,
            'activated_by' => $actor->id,
            'activated_at' => now(),
            'deactivated_by' => null,
            'deactivated_at' => null,
        ]);
    }

    public function deactivate(User $actor): void
    {
        $this->update([
            'status' => PoolMemberStatus::Inactive->value,
            'deactivated_by' => $actor->id,
            'deactivated_at' => now(),
        ]);
    }
}
