<?php

namespace Tests\Feature\Domain;

use App\Models\CompetitionPackage;
use App\Models\CompetitionPackageItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCompetitionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_user_without_package_can_access_only_world_cup_by_tier_fallback(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'subscription_tier' => 1,
            'competition_package_id' => null,
        ]);

        $this->assertTrue($user->canAccessCompetition('WC'));
        $this->assertFalse($user->canAccessCompetition('BSA'));
    }

    public function test_tier2_user_without_package_keeps_backward_compatible_access(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'subscription_tier' => 2,
            'competition_package_id' => null,
        ]);

        $this->assertTrue($user->canAccessCompetition('WC'));
        $this->assertTrue($user->canAccessCompetition('BSA'));
    }

    public function test_user_with_package_uses_package_rules(): void
    {
        $package = CompetitionPackage::create([
            'code' => 'basic',
            'name' => 'Plano Básico',
            'active' => true,
        ]);

        CompetitionPackageItem::create([
            'competition_package_id' => $package->id,
            'competition_id' => null,
            'competition_code' => 'WC',
        ]);

        $user = User::factory()->create([
            'is_admin' => false,
            'subscription_tier' => 2,
            'competition_package_id' => $package->id,
        ]);

        $this->assertTrue($user->canAccessCompetition('WC'));
        $this->assertFalse($user->canAccessCompetition('BSA'));
    }

    public function test_admin_can_access_any_competition(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
            'subscription_tier' => 1,
            'competition_package_id' => null,
        ]);

        $this->assertTrue($user->canAccessCompetition('WC'));
        $this->assertTrue($user->canAccessCompetition('BSA'));
        $this->assertTrue($user->canAccessCompetition('UCL'));
    }
}
