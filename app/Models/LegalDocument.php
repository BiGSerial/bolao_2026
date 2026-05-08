<?php

namespace App\Models;

use App\Enums\LegalDocumentType;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalDocument extends Model
{
    protected $fillable = [
        'type',
        'title',
        'version',
        'content',
        'is_active',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => LegalDocumentType::class,
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            if (! $document->isPublished()) {
                return;
            }

            $immutableFields = ['type', 'title', 'version', 'content'];

            foreach ($immutableFields as $field) {
                if ($document->isDirty($field)) {
                    throw new DomainException('Nao e permitido editar campos de documento juridico publicado. Crie uma nova versao.');
                }
            }
        });
    }

    public function scopeOfType(Builder $query, LegalDocumentType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(UserLegalAcceptance::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
