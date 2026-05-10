<?php

namespace Tests\Feature\User;

use App\Http\Middleware\EnsureLegalAcceptance;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFlowRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'must_change_password' => false,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'status' => 'pending',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_profile_update_keeps_user_access_data_intact(): void
    {
        $this->withoutMiddleware(EnsureLegalAcceptance::class);
        $this->withoutMiddleware(EnsurePasswordChanged::class);

        $user = User::factory()->create([
            'status' => 'active',
            'subscription_tier' => 2,
            'competition_package_id' => null,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->patch('/profile', [
            'name' => 'Nome Atualizado',
            'email' => 'novo@example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Nome Atualizado', $user->name);
        $this->assertSame('novo@example.com', $user->email);
        $this->assertSame('active', $user->status);
        $this->assertSame(2, (int) $user->subscription_tier);
        $this->assertNull($user->competition_package_id);
    }

    public function test_registration_requires_display_name_and_creates_user_with_default_security_flags(): void
    {
        $response = $this->post('/register', [
            'name' => 'Novo Usuario',
            'display_name' => 'Novo',
            'email' => 'novo.registro@example.com',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'novo.registro@example.com')->firstOrFail();

        $this->assertTrue((bool) $user->must_change_password);
        $this->assertSame('pending', $user->status);
        $this->assertAuthenticatedAs($user);
    }
}
