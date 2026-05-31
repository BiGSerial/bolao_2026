<?php

namespace App\Http\Controllers\Api\V1\Legal;

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalDocumentApiController extends Controller
{
    private const ALLOWED_TYPES = [
        'eula'           => LegalDocumentType::Eula,
        'privacy-policy' => LegalDocumentType::PrivacyPolicy,
        'disclaimer'     => LegalDocumentType::Disclaimer,
    ];

    public function show(Request $request, string $type): JsonResponse
    {
        $docType = self::ALLOWED_TYPES[$type] ?? null;

        if ($docType === null) {
            return ApiResponse::error($request, 'NOT_FOUND', 'Documento não encontrado.', 404);
        }

        $document = LegalDocument::query()
            ->active()
            ->ofType($docType)
            ->latest('published_at')
            ->first(['id', 'type', 'title', 'version', 'content', 'published_at']);

        if (! $document) {
            return ApiResponse::error($request, 'NOT_FOUND', 'Documento não publicado.', 404);
        }

        return ApiResponse::success($request, [
            'id'           => $document->id,
            'type'         => $document->type?->value,
            'title'        => $document->title,
            'version'      => $document->version,
            'content'      => $document->content,
            'published_at' => $document->published_at?->toIso8601String(),
        ]);
    }
}
