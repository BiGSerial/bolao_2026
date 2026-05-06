<?php

namespace Tests\Feature\Security;

use App\Livewire\Management\MyPoolsManager;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_model_does_not_allow_privileged_mass_assignment_fields(): void
    {
        $user = new User();

        $this->assertFalse($user->isFillable('is_admin'));
        $this->assertFalse($user->isFillable('status'));
        $this->assertFalse($user->isFillable('must_change_password'));
        $this->assertFalse($user->isFillable('password_changed_at'));
    }

    public function test_register_endpoint_ignores_privileged_fields_from_payload(): void
    {
        $response = $this->post('/register', [
            'name' => 'Usuario Teste',
            'email' => 'seguranca@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'is_admin' => true,
            'status' => 'active',
            'must_change_password' => true,
            'password_changed_at' => now()->subDay()->toDateTimeString(),
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'seguranca@example.com')->firstOrFail();

        $this->assertFalse((bool) $user->is_admin);
        $this->assertSame('pending', $user->status);
        $this->assertFalse((bool) $user->must_change_password);
        $this->assertNull($user->password_changed_at);
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['is_admin' => true]);

        $this->actingAs($actor);

        $this->get('/admin/usuarios')->assertForbidden();
        $this->get('/admin/api-sync')->assertForbidden();
        $this->get('/admin/jogos/correcao-manual')->assertForbidden();
        $this->post("/admin/usuarios/{$target->id}/aprovar")->assertForbidden();
    }

    public function test_manager_cannot_set_member_sector_outside_pool_allowed_list(): void
    {
        $owner = User::factory()->create();
        $memberUser = User::factory()->create();

        $pool = Pool::create([
            'owner_id' => $owner->id,
            'name' => 'Pool Seguranca',
            'slug' => 'pool-seguranca-'.uniqid(),
            'visibility' => 'invite_only',
            'status' => 'active',
            'invite_code' => strtoupper(substr(uniqid('SC'), 0, 8)),
            'allow_prediction_changes' => true,
            'prediction_lock_minutes' => 120,
            'allow_pending_member_predictions' => true,
            'stage' => 'GROUP_STAGE',
            'sectors' => ['TI', 'RH'],
        ]);

        $pool->members()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $member = $pool->members()->create([
            'user_id' => $memberUser->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $this->actingAs($owner);

        Livewire::test(MyPoolsManager::class)
            ->set('selectedPoolId', $pool->id)
            ->call('updateSector', $member->id, 'Setor Inexistente');

        $member->refresh();
        $this->assertNull($member->sector);

        Livewire::test(MyPoolsManager::class)
            ->set('selectedPoolId', $pool->id)
            ->call('updateSector', $member->id, 'TI');

        $this->assertSame('TI', $member->fresh()->sector);
    }
}
