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
        return ApiResponse::success($request, [
            'current_version' => (string) config('app.version', 'dev'),
            'minimum_supported_version' => (string) config('pwa.minimum_supported_version', config('app.version', 'dev')),
            'build_id' => (string) config('pwa.build_id', config('app.version', 'dev')),
            'feature_flags' => (array) config('pwa.feature_flags', []),
            'kill_switch' => (bool) config('pwa.kill_switch', false),
        ]);
    }
}
