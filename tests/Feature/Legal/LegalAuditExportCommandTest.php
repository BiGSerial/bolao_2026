<?php

namespace Tests\Feature\Legal;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserLegalAcceptance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegalAuditExportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_command_generates_csv_json_and_manifest_with_checksums(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $document = LegalDocument::query()->create([
            'type' => LegalDocumentType::Eula->value,
            'title' => 'Termos Teste',
            'version' => '1.0',
            'content' => 'Conteudo juridico de teste',
            'is_active' => true,
            'published_at' => now(),
            'created_by' => $user->id,
        ]);

        UserLegalAcceptance::query()->create([
            'user_id' => $user->id,
            'legal_document_id' => $document->id,
            'accepted_at' => now(),
            'acceptance_method' => 'mandatory_acceptance_gate',
            'accepted_document_version' => '1.0',
            'accepted_document_hash' => hash('sha256', 'Conteudo juridico de teste'),
            'accepted_document_snapshot' => 'Conteudo juridico de teste',
            'ip_hash' => str_repeat('a', 64),
            'user_agent_hash' => str_repeat('b', 64),
            'acceptance_context' => [
                'route' => 'legal.acceptance.store',
                'path' => 'legal/acceptance',
            ],
        ]);

        $this->artisan('legal:export-audit', [
            '--output' => 'tmp-legal-audit-tests',
        ])->assertExitCode(0);

        $all = Storage::disk('local')->allFiles('tmp-legal-audit-tests');

        $this->assertNotEmpty($all);
        $this->assertTrue(collect($all)->contains(fn (string $p) => str_ends_with($p, '.csv')));
        $this->assertTrue(collect($all)->contains(fn (string $p) => str_ends_with($p, '.json')));
        $this->assertTrue(collect($all)->contains(fn (string $p) => str_ends_with($p, '.manifest.json')));

        $manifestPath = collect($all)->first(fn (string $p) => str_ends_with($p, '.manifest.json'));
        $this->assertNotNull($manifestPath);

        $manifestRaw = Storage::disk('local')->get((string) $manifestPath);
        $manifest = json_decode($manifestRaw, true);

        $this->assertIsArray($manifest);
        $this->assertCount(2, $manifest['files'] ?? []);

        foreach ($manifest['files'] as $file) {
            $this->assertArrayHasKey('path', $file);
            $this->assertArrayHasKey('sha256', $file);
            $this->assertArrayHasKey('size', $file);
            $this->assertSame(64, strlen((string) $file['sha256']));
        }
    }
}
