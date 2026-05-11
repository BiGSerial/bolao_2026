<?php

namespace App\Models;

use App\Notifications\CustomVerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'display_name',
        'area',
        'email',
        'password',
        'status',
        'must_change_password',
        'password_changed_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'subscription_tier' => 'integer',
            'competition_package_id' => 'integer',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    public function canAccessCompetition(string $code): bool
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === 'WC') {
            return true;
        }

        if ((bool) $this->is_admin) {
            return true;
        }

        $enabled = (bool) config('football-data.competitions.'.$normalized.'.enabled', false);
        if (! $enabled) {
            return false;
        }

        if ($this->competition_package_id) {
            $package = $this->competitionPackage;
            if (! $package || ! $package->active) {
                return false;
            }

            return $package->items()
                ->whereRaw('UPPER(competition_code) = ?', [$normalized])
                ->exists();
        }

        return (int) ($this->subscription_tier ?? 1) >= 2;
    }

    public function competitionPackage(): BelongsTo
    {
        return $this->belongsTo(CompetitionPackage::class);
    }

    public function poolMemberships(): HasMany
    {
        return $this->hasMany(PoolMember::class);
    }

    public function ownedPools(): HasMany
    {
        return $this->hasMany(Pool::class, 'owner_id');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    public function poolRankings(): HasMany
    {
        return $this->hasMany(PoolRanking::class);
    }

    public function legalDocumentsCreated(): HasMany
    {
        return $this->hasMany(LegalDocument::class, 'created_by');
    }

    public function legalAcceptances(): HasMany
    {
        return $this->hasMany(UserLegalAcceptance::class);
    }

    public function getPublicNameAttribute(): string
    {
        $displayName = trim((string) $this->display_name);
        if ($displayName !== '') {
            return $displayName;
        }

        $fullName = trim((string) $this->name);
        if ($fullName === '') {
            return '—';
        }

        return (string) preg_split('/\s+/u', $fullName)[0];
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new CustomVerifyEmailNotification());
    }
}
