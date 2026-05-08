<?php

namespace App\Http\Middleware;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\UserLegalAcceptance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegalAcceptance
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ((bool) $user->is_admin) {
            return $next($request);
        }

        if (
            $request->routeIs('legal.acceptance.*') ||
            $request->routeIs('logout') ||
            $request->routeIs('legal.*') ||
            $request->routeIs('about')
        ) {
            return $next($request);
        }

        $activeDocuments = LegalDocument::query()
            ->active()
            ->whereIn('type', [
                LegalDocumentType::Eula->value,
                LegalDocumentType::PrivacyPolicy->value,
            ])
            ->get(['id', 'type']);

        if ($activeDocuments->count() < 2) {
            return redirect()->route('legal.acceptance.show');
        }

        $acceptedCount = UserLegalAcceptance::query()
            ->where('user_id', $user->id)
            ->whereIn('legal_document_id', $activeDocuments->pluck('id'))
            ->count();

        if ($acceptedCount < 2) {
            return redirect()->route('legal.acceptance.show');
        }

        return $next($request);
    }
}
