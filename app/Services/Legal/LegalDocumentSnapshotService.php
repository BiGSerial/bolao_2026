<?php

namespace App\Services\Legal;

use App\Models\LegalDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LegalDocumentSnapshotService
{
    public function storePublishedSnapshot(LegalDocument $document): void
    {
        $publishedAt = $document->published_at ?? now();
        $type = Str::lower($document->type->value);
        $version = Str::of($document->version)->replace(['/', '\\', ' '], '-')->toString();
        $timestamp = $publishedAt->format('Ymd_His');

        $baseDir = "legal-documents/{$type}";
        $baseName = "v{$version}_{$timestamp}";

        $metadata = [
            'id' => $document->id,
            'type' => $document->type->value,
            'title' => $document->title,
            'slug' => $document->slug,
            'version' => $document->version,
            'content_hash' => $document->content_hash,
            'is_active' => (bool) $document->is_active,
            'published_at' => $publishedAt->toIso8601String(),
            'created_by' => $document->created_by,
            'created_at' => optional($document->created_at)?->toIso8601String(),
            'updated_at' => optional($document->updated_at)?->toIso8601String(),
        ];

        Storage::disk('local')->put("{$baseDir}/{$baseName}.md", $document->content);
        Storage::disk('local')->put(
            "{$baseDir}/{$baseName}.json",
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
