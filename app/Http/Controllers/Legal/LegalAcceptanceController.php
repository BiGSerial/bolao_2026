<?php

namespace App\Http\Controllers\Legal;

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use App\Models\UserLegalAcceptance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LegalAcceptanceController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $eula = LegalDocument::query()->active()->ofType(LegalDocumentType::Eula)->latest('published_at')->first();
        $privacyPolicy = LegalDocument::query()->active()->ofType(LegalDocumentType::PrivacyPolicy)->latest('published_at')->first();

        if ($eula && $privacyPolicy) {
            $acceptedCount = UserLegalAcceptance::query()
                ->where('user_id', $user->id)
                ->whereIn('legal_document_id', [$eula->id, $privacyPolicy->id])
                ->count();

            if ($acceptedCount === 2) {
                return redirect()->intended(route('dashboard', absolute: false));
            }
        }

        return view('legal.acceptance', compact('eula', 'privacyPolicy'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $validated = $request->validate([
            'accept_eula' => ['accepted'],
            'accept_privacy_policy' => ['accepted'],
        ], [
            'accept_eula.accepted' => 'Você deve aceitar os Termos de Uso para continuar.',
            'accept_privacy_policy.accepted' => 'Você deve aceitar a Política de Privacidade para continuar.',
        ]);

        $eula = LegalDocument::query()->active()->ofType(LegalDocumentType::Eula)->latest('published_at')->first();
        $privacyPolicy = LegalDocument::query()->active()->ofType(LegalDocumentType::PrivacyPolicy)->latest('published_at')->first();

        if (! $eula || ! $privacyPolicy) {
            return back()->withErrors([
                'legal_documents' => 'Documentos jurídicos ainda não publicados. Contate o administrador.',
            ]);
        }

        DB::transaction(function () use ($request, $user, $eula, $privacyPolicy): void {
            foreach ([$eula, $privacyPolicy] as $document) {
                UserLegalAcceptance::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'legal_document_id' => $document->id,
                ], [
                    'accepted_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                ]);
            }
        });

        unset($validated);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
