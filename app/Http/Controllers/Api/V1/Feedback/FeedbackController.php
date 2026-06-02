<?php

namespace App\Http\Controllers\Api\V1\Feedback;

use App\Http\Controllers\Controller;
use App\Notifications\FeedbackReceivedNotification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class FeedbackController extends Controller
{
    private const CONTACT_EMAIL = 'contato@vixforge.com.br';

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

        Notification::route('mail', self::CONTACT_EMAIL)->notify(
            new FeedbackReceivedNotification(
                category:    $validated['category'],
                description: $validated['description'],
                userId:      (int) $user->id,
                userEmail:   (string) $user->email,
                userName:    (string) $user->public_name,
            )
        );

        return ApiResponse::success($request, ['message' => 'Feedback recebido. Obrigado!']);
    }
}
