<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationEnvelopeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_error_uses_api_envelope_for_v1_routes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/matches?per_page=999');

        $response
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.message', 'Dados inválidos.')
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details' => ['fields']],
                'meta' => ['request_id', 'version'],
            ]);
    }
}
