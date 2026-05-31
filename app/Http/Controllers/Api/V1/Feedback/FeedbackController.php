<?php

namespace App\Http\Controllers\Api\V1\Feedback;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FeedbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category'    => ['required', 'in:bug,suggestion,other'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $user = $request->user();

        Log::channel('single')->info('[feedback]', [
            'user_id'     => $user->id,
            'user_email'  => $user->email,
            'category'    => $validated['category'],
            'description' => $validated['description'],
            'user_agent'  => $request->userAgent(),
            'ip'          => $request->ip(),
        ]);

        return ApiResponse::success($request, ['message' => 'Feedback recebido. Obrigado!']);
    }
}
