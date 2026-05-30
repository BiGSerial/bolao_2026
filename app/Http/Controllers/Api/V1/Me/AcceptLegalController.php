<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use App\Models\UserLegalAcceptance;
use App\Services\Legal\LegalAcceptanceEvidenceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcceptLegalController extends Controller
{
    public function __construct(
        private readonly LegalAcceptanceEvidenceService $evidenceService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['integer', 'exists:legal_documents,id'],
        ]);

        $user = $request->user();

        $documents = LegalDocument::query()
            ->active()
            ->whereIn('id', $request->input('document_ids'))
            ->whereIn('type', [
                LegalDocumentType::Eula->value,
                LegalDocumentType::PrivacyPolicy->value,
            ])
            ->get();

        if ($documents->isEmpty()) {
            return ApiResponse::error($request, 'INVALID_DOCUMENTS', 'Documentos inválidos ou não ativos.', 422);
        }

        DB::transaction(function () use ($request, $user, $documents): void {
            foreach ($documents as $document) {
                $evidence = $this->evidenceService->buildEvidence(
                    request: $request,
                    document: $document,
                    method: 'pwa_acceptance_gate',
                    context: ['source' => 'pwa'],
                );

                UserLegalAcceptance::query()->firstOrCreate(
                    ['user_id' => $user->id, 'legal_document_id' => $document->id],
                    $evidence,
                );
            }
        });

        // Verifica se ainda há documentos pendentes
        $pending = $this->checkStillPending($user);

        return ApiResponse::success($request, [
            'accepted'      => $documents->count(),
            'legal_pending' => $pending,
        ]);
    }

    private function checkStillPending(mixed $user): bool
    {
        $required = LegalDocument::query()
            ->active()
            ->whereIn('type', [LegalDocumentType::Eula->value, LegalDocumentType::PrivacyPolicy->value])
            ->orderByDesc('published_at')
            ->get(['id', 'type'])
            ->unique('type');

        if ($required->count() < 2) {
            return false;
        }

        $acceptedCount = UserLegalAcceptance::query()
            ->where('user_id', $user->id)
            ->whereIn('legal_document_id', $required->pluck('id'))
            ->count();

        return $acceptedCount < 2;
    }
}
