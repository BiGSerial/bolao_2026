<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'public_key' => ['nullable', 'string'],
            'auth_token' => ['nullable', 'string'],
            'content_encoding' => ['nullable', 'string'],
        ]);

        $subscription = PushSubscription::query()->updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => $request->user()->id,
                'public_key' => $validated['public_key'] ?? null,
                'auth_token' => $validated['auth_token'] ?? null,
                'content_encoding' => $validated['content_encoding'] ?? 'aesgcm',
            ]
        );

        return ApiResponse::success($request, [
            'id' => $subscription->id,
            'endpoint' => $subscription->endpoint,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        PushSubscription::query()
            ->where('endpoint', $validated['endpoint'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(null, 204);
    }
}
