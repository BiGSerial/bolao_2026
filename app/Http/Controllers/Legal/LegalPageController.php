<?php

namespace App\Http\Controllers\Legal;

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use Illuminate\View\View;

class LegalPageController extends Controller
{
    public function eula(): View
    {
        return $this->renderDocument(LegalDocumentType::Eula);
    }

    public function terms(): View
    {
        return $this->renderDocument(LegalDocumentType::Eula);
    }

    public function privacyPolicy(): View
    {
        return $this->renderDocument(LegalDocumentType::PrivacyPolicy);
    }

    public function about(): View
    {
        return view('legal.about');
    }

    private function renderDocument(LegalDocumentType $type): View
    {
        $document = LegalDocument::query()
            ->active()
            ->ofType($type)
            ->latest('published_at')
            ->first();

        return view('legal.document', [
            'document' => $document,
            'documentType' => $type,
        ]);
    }
}
