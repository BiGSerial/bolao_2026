<?php

namespace App\Services\Legal;

use App\Models\LegalDocument;
use Illuminate\Http\Request;

class LegalAcceptanceEvidenceService
{
    /**
     * @return array<string, mixed>
     */
    public function buildEvidence(
        Request $request,
        LegalDocument $document,
        string $method,
        array $context = [],
    ): array {
        $rawIp = (string) ($request->ip() ?? '');
        $rawUserAgent = (string) ($request->userAgent() ?? '');

        $pepper = (string) config('app.key', '');

        return [
            'accepted_at' => now(),
            'acceptance_method' => $method,
            'accepted_document_version' => (string) $document->version,
            'accepted_document_hash' => (string) ($document->content_hash ?: hash('sha256', (string) $document->content)),
            'accepted_document_snapshot' => (string) $document->content,
            // Keep legacy raw fields nullable for backward compatibility and LGPD minimization.
            'ip_address' => null,
            'user_agent' => null,
            'ip_hash' => $rawIp !== '' ? hash_hmac('sha256', $rawIp, $pepper) : null,
            'user_agent_hash' => $rawUserAgent !== '' ? hash_hmac('sha256', $rawUserAgent, $pepper) : null,
            'acceptance_context' => [
                'route' => optional($request->route())->getName(),
                'path' => $request->path(),
                'source' => $context['source'] ?? 'web',
            ] + $context,
        ];
    }
}
