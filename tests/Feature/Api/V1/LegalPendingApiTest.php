<?php

namespace Tests\Feature\Api\V1;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserLegalAcceptance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPendingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_pending_documents_for_user_without_acceptances(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $eula = $this->createActiveLegalDocument(LegalDocumentType::Eula, 'v1.0.0');
        $privacy = $this->createActiveLegalDocument(LegalDocumentType::PrivacyPolicy, 'v1.0.0');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/legal/pending');

        $response
            ->assertOk()
            ->assertJsonPath('data.pending', true)
            ->assertJsonPath('data.accepted_count', 0)
            ->assertJsonPath('data.required_count', 2)
            ->assertJsonCount(2, 'data.documents')
            ->assertJsonFragment(['id' => $eula->id, 'type' => LegalDocumentType::Eula->value])
            ->assertJsonFragment(['id' => $privacy->id, 'type' => LegalDocumentType::PrivacyPolicy->value]);
    }

    public function test_returns_no_pending_when_required_documents_are_accepted(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $eula = $this->createActiveLegalDocument(LegalDocumentType::Eula, 'v1.0.0');
        $privacy = $this->createActiveLegalDocument(LegalDocumentType::PrivacyPolicy, 'v1.0.0');

        UserLegalAcceptance::query()->create([
            'user_id' => $user->id,
            'legal_document_id' => $eula->id,
            'accepted_at' => now(),
            'acceptance_method' => 'api_test',
        ]);

        UserLegalAcceptance::query()->create([
            'user_id' => $user->id,
            'legal_document_id' => $privacy->id,
            'accepted_at' => now(),
            'acceptance_method' => 'api_test',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/legal/pending');

        $response
            ->assertOk()
            ->assertJsonPath('data.pending', false)
            ->assertJsonPath('data.accepted_count', 2)
            ->assertJsonPath('data.required_count', 2)
            ->assertJsonCount(0, 'data.documents');
    }

    public function test_admin_user_has_no_pending_legal_documents(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('admin-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/legal/pending');

        $response
            ->assertOk()
            ->assertJsonPath('data.pending', false)
            ->assertJsonPath('data.required_count', 2)
            ->assertJsonCount(0, 'data.documents');
    }

    private function createActiveLegalDocument(LegalDocumentType $type, string $version): LegalDocument
    {
        return LegalDocument::query()->create([
            'type' => $type->value,
            'title' => $type->label(),
            'slug' => strtolower($type->value).'-'.$version,
            'version' => $version,
            'content' => "Conteudo {$type->value} {$version}",
            'is_active' => true,
            'published_at' => now(),
        ]);
    }
}
