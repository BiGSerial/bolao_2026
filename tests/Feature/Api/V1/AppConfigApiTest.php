<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class AppConfigApiTest extends TestCase
{
    public function test_returns_app_config_with_default_shape(): void
    {
        config()->set('app.version', '2026.05.23');
        config()->set('pwa.minimum_supported_version', '2026.05.20');
        config()->set('pwa.build_id', 'build-abc123');
        config()->set('pwa.kill_switch', false);
        config()->set('pwa.feature_flags', [
            'pwa_enabled' => true,
            'offline_predictions_queue' => false,
            'push_notifications' => false,
            'realtime_rankings' => false,
        ]);

        $response = $this->getJson('/api/v1/app-config');

        $response
            ->assertOk()
            ->assertJsonPath('data.current_version', '2026.05.23')
            ->assertJsonPath('data.minimum_supported_version', '2026.05.20')
            ->assertJsonPath('data.build_id', 'build-abc123')
            ->assertJsonPath('data.kill_switch', false)
            ->assertJsonPath('data.feature_flags.pwa_enabled', true)
            ->assertJsonStructure([
                'data' => [
                    'current_version',
                    'minimum_supported_version',
                    'build_id',
                    'feature_flags',
                    'kill_switch',
                ],
                'meta' => ['request_id', 'version'],
            ]);
    }

    public function test_returns_kill_switch_true_when_enabled(): void
    {
        config()->set('app.version', '2026.05.23');
        config()->set('pwa.minimum_supported_version', '2026.05.23');
        config()->set('pwa.build_id', 'build-def456');
        config()->set('pwa.kill_switch', true);
        config()->set('pwa.feature_flags', [
            'pwa_enabled' => false,
            'offline_predictions_queue' => false,
            'push_notifications' => false,
            'realtime_rankings' => false,
        ]);

        $response = $this->getJson('/api/v1/app-config');

        $response
            ->assertOk()
            ->assertJsonPath('data.kill_switch', true)
            ->assertJsonPath('data.feature_flags.pwa_enabled', false);
    }
}
