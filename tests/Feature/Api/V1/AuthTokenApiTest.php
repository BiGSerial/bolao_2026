<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTokenApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_sanctum_token(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'secret-123',
            'must_change_password' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'user@example.com',
            'password' => 'secret-123',
            'device_name' => 'iphone-16',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.flags.must_change_password', true)
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'user' => ['id', 'name', 'email'], 'flags'],
                'meta' => ['request_id', 'version'],
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_returns_uniform_error_for_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'valid@example.com',
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'valid@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS')
            ->assertJsonPath('error.message', 'Credenciais inválidas.')
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details'],
                'meta' => ['request_id', 'version'],
            ]);
    }

    public function test_authenticated_user_can_logout_and_revoke_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertNull(PersonalAccessToken::query()->first());
    }

    public function test_authenticated_user_can_refresh_token_and_old_token_is_revoked(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->createToken('old-device')->plainTextToken;
        $oldTokenRecordId = $user->tokens()->latest('id')->value('id');

        $refreshResponse = $this->withHeader('Authorization', 'Bearer '.$oldToken)
            ->postJson('/api/v1/auth/refresh', [
                'device_name' => 'new-device',
            ]);

        $refreshResponse
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id);

        $newToken = (string) $refreshResponse->json('data.token');
        $this->assertNotSame($oldToken, $newToken);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldTokenRecordId]);
    }
}
