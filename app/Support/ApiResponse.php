<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiResponse
{
    public static function success(Request $request, array $data = [], int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => array_merge([
                'request_id' => self::requestId($request),
                'version' => 'v1',
            ], $meta),
        ], $status);
    }

    public static function error(
        Request $request,
        string $code,
        string $message,
        int $status,
        array $details = []
    ): JsonResponse {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
            ],
            'meta' => [
                'request_id' => self::requestId($request),
                'version' => 'v1',
            ],
        ], $status);
    }

    private static function requestId(Request $request): string
    {
        $requestId = $request->headers->get('X-Request-Id');

        return is_string($requestId) && $requestId !== '' ? $requestId : (string) Str::uuid();
    }
}
