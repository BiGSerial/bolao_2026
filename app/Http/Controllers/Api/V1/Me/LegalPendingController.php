<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use App\Models\UserLegalAcceptance;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalPendingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error($request, 'AUTH_UNAUTHENTICATED', 'Usuário não autenticado.', 401);
        }

        if ((bool) $user->is_admin) {
            return ApiResponse::success($request, [
                'pending' => false,
                'accepted_count' => 0,
                'required_count' => 2,
                'documents' => [],
            ]);
        }

        $requiredDocuments = LegalDocument::query()
            ->active()
            ->whereIn('type', [
                LegalDocumentType::Eula->value,
                LegalDocumentType::PrivacyPolicy->value,
            ])
            ->orderBy('type')
            ->orderByDesc('published_at')
            ->get(['id', 'type', 'title', 'slug', 'version', 'published_at'])
            ->unique('type')
            ->values();

        if ($requiredDocuments->count() < 2) {
            return ApiResponse::success($request, [
                'pending' => true,
                'accepted_count' => 0,
                'required_count' => 2,
                'documents' => [],
                'missing_documents' => true,
            ]);
        }

        $acceptedIds = UserLegalAcceptance::query()
            ->where('user_id', $user->id)
            ->whereIn('legal_document_id', $requiredDocuments->pluck('id'))
            ->pluck('legal_document_id')
            ->all();

        $pendingDocuments = $requiredDocuments
            ->reject(fn (LegalDocument $document) => in_array($document->id, $acceptedIds, true))
            ->map(fn (LegalDocument $document): array => [
                'id' => $document->id,
                'type' => $document->type?->value ?? (string) $document->type,
                'title' => (string) $document->title,
                'slug' => (string) $document->slug,
                'version' => (string) $document->version,
                'published_at' => optional($document->published_at)?->toIso8601String(),
            ])
            ->values()
            ->all();

        return ApiResponse::success($request, [
            'pending' => count($pendingDocuments) > 0,
            'accepted_count' => count($acceptedIds),
            'required_count' => 2,
            'documents' => $pendingDocuments,
        ]);
    }
}
