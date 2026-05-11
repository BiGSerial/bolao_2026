<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLegalAcceptance extends Model
{
    protected $fillable = [
        'user_id',
        'legal_document_id',
        'accepted_at',
        'acceptance_method',
        'accepted_document_version',
        'accepted_document_hash',
        'accepted_document_snapshot',
        'ip_address',
        'ip_hash',
        'user_agent',
        'user_agent_hash',
        'acceptance_context',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'acceptance_context' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legalDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class);
    }

    public function hasEvidenceIntegrity(): bool
    {
        if (! is_string($this->accepted_document_hash) || $this->accepted_document_hash === '') {
            return false;
        }

        return hash_equals(
            $this->accepted_document_hash,
            hash('sha256', (string) $this->accepted_document_snapshot)
        );
    }
}
