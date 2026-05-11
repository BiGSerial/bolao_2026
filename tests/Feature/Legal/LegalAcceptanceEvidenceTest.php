<?php

namespace Tests\Feature\Legal;

use App\Enums\LegalDocumentType;
use App\Http\Middleware\EnsureLegalAcceptance;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserLegalAcceptance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalAcceptanceEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mandatory_acceptance_stores_pseudonymized_and_immutable_evidence(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'must_change_password' => false,
        ]);

        $eula = LegalDocument::query()->create([
            'type' => LegalDocumentType::Eula->value,
            'title' => 'Termos 2026',
            'version' => '1.0',
            'content' => 'Conteudo dos termos versao 1.0',
            'is_active' => true,
            'published_at' => now(),
            'created_by' => $user->id,
        ]);

        $privacy = LegalDocument::query()->create([
            'type' => LegalDocumentType::PrivacyPolicy->value,
            'title' => 'Privacidade 2026',
            'version' => '1.0',
            'content' => 'Conteudo da politica versao 1.0',
            'is_active' => true,
            'published_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->withoutMiddleware(EnsureLegalAcceptance::class);
        $this->withoutMiddleware(EnsurePasswordChanged::class);

        $response = $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('User-Agent', 'PHPUnit Agent 1.0')
            ->post(route('legal.acceptance.store', absolute: false), [
                'accept_eula' => '1',
                'accept_privacy_policy' => '1',
            ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $acceptances = UserLegalAcceptance::query()
            ->where('user_id', $user->id)
            ->orderBy('legal_document_id')
            ->get();

        $this->assertCount(2, $acceptances);

        foreach ($acceptances as $acceptance) {
            $document = $acceptance->legalDocument;

            $this->assertNotNull($document);
            $this->assertSame('mandatory_acceptance_gate', $acceptance->acceptance_method);
            $this->assertSame($document->version, $acceptance->accepted_document_version);
            $this->assertSame(hash('sha256', (string) $document->content), $acceptance->accepted_document_hash);
            $this->assertSame((string) $document->content, $acceptance->accepted_document_snapshot);
            $this->assertNull($acceptance->ip_address);
            $this->assertNull($acceptance->user_agent);
            $this->assertNotNull($acceptance->ip_hash);
            $this->assertNotNull($acceptance->user_agent_hash);
            $this->assertTrue($acceptance->hasEvidenceIntegrity());
        }

        $this->assertSame(hash('sha256', (string) $eula->content), $eula->fresh()->content_hash);
        $this->assertSame(hash('sha256', (string) $privacy->content), $privacy->fresh()->content_hash);
    }

    public function test_public_legal_routes_are_available_for_all_document_types(): void
    {
        $this->get(route('legal.terms', absolute: false))->assertOk();
        $this->get(route('legal.privacy-policy', absolute: false))->assertOk();
        $this->get(route('legal.disclaimer', absolute: false))->assertOk();
        $this->get(route('legal.confidentiality-policy', absolute: false))->assertOk();
    }
}
