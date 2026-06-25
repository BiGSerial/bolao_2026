<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppConfigController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $currentVersion = (string) config('app.version', 'dev');
        $configuredBuildId = (string) config('pwa.build_id', '');
        $buildId = trim($configuredBuildId) !== '' && $configuredBuildId !== 'dev'
            ? $configuredBuildId
            : $currentVersion;

        return ApiResponse::success($request, [
            'current_version' => $currentVersion,
            'minimum_supported_version' => (string) config('pwa.minimum_supported_version', config('app.version', 'dev')),
            'build_id' => $buildId,
            'feature_flags' => (array) config('pwa.feature_flags', []),
            'kill_switch' => (bool) config('pwa.kill_switch', false),
        ]);
    }
}
