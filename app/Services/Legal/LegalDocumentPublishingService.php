<?php

namespace App\Services\Legal;

use App\Models\LegalDocument;
use DomainException;
use Illuminate\Support\Facades\DB;

class LegalDocumentPublishingService
{
    public function __construct(
        private readonly LegalDocumentSnapshotService $snapshotService,
    ) {}

    public function publish(LegalDocument $document): LegalDocument
    {
        if ($document->published_at !== null) {
            throw new DomainException('Documento juridico ja publicado nao pode ser republicado. Crie uma nova versao.');
        }

        $publishedDocument = DB::transaction(function () use ($document): LegalDocument {
            LegalDocument::query()
                ->where('type', $document->type->value)
                ->where('is_active', true)
                ->whereKeyNot($document->id)
                ->update(['is_active' => false]);

            $document->forceFill([
                'is_active' => true,
                'published_at' => now(),
            ])->save();

            return $document->fresh();
        });

        // Automatic file versioning snapshot for legal auditing.
        $this->snapshotService->storePublishedSnapshot($publishedDocument);

        return $publishedDocument;
    }
}
