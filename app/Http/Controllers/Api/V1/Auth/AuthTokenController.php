<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserLegalAcceptance;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthTokenController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $this->ensureIsNotRateLimited($request, (string) $payload['login']);

        $user = User::query()
            ->where('email', $payload['login'])
            ->orWhere('name', $payload['login'])
            ->first();

        if (! $user || ! Hash::check((string) $payload['password'], (string) $user->password)) {
            $this->hitRateLimiters($request, (string) $payload['login']);

            return ApiResponse::error(
                request: $request,
                code: 'AUTH_INVALID_CREDENTIALS',
                message: 'Credenciais inválidas.',
                status: 401,
            );
        }

        if ((string) $user->status !== 'active') {
            $this->hitRateLimiters($request, (string) $payload['login']);

            return ApiResponse::error(
                request: $request,
                code: 'AUTH_USER_INACTIVE',
                message: 'Sua conta está sem acesso à plataforma.',
                status: 403,
            );
        }

        $this->clearRateLimiters($request, (string) $payload['login']);

        $token = $user->createToken((string) ($payload['device_name'] ?? 'pwa-web'))->plainTextToken;

        return ApiResponse::success($request, [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'flags' => [
                'must_change_password' => (bool) $user->must_change_password,
                'legal_pending' => $this->hasLegalPending($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json(null, 204);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user?->currentAccessToken();

        if (! $user || ! $currentToken) {
            return ApiResponse::error(
                request: $request,
                code: 'AUTH_UNAUTHENTICATED',
                message: 'Usuário não autenticado.',
                status: 401,
            );
        }

        $payload = $request->validate([
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $newToken = $user->createToken((string) ($payload['device_name'] ?? $currentToken->name ?? 'pwa-web'))->plainTextToken;
        $currentToken->delete();

        return ApiResponse::success($request, [
            'token' => $newToken,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'flags' => [
                'must_change_password' => (bool) $user->must_change_password,
                'legal_pending' => $this->hasLegalPending($user),
            ],
        ]);
    }

    private function hasLegalPending(User $user): bool
    {
        if ((bool) $user->is_admin) {
            return false;
        }

        $activeDocuments = LegalDocument::query()
            ->active()
            ->whereIn('type', [
                LegalDocumentType::Eula->value,
                LegalDocumentType::PrivacyPolicy->value,
            ])
            ->get(['id']);

        if ($activeDocuments->count() < 2) {
            return true;
        }

        $acceptedCount = UserLegalAcceptance::query()
            ->where('user_id', $user->id)
            ->whereIn('legal_document_id', $activeDocuments->pluck('id'))
            ->count();

        return $acceptedCount < 2;
    }

    private function ensureIsNotRateLimited(Request $request, string $login): void
    {
        $identityMaxAttempts = (int) config('auth.login_rate_limit.max_attempts_per_identity', 5);
        $identityDecaySeconds = (int) config('auth.login_rate_limit.decay_seconds_per_identity', 60);
        $ipMaxAttempts = (int) config('auth.login_rate_limit.max_attempts_per_ip', 25);
        $ipDecaySeconds = (int) config('auth.login_rate_limit.decay_seconds_per_ip', 60);

        $identityBlocked = RateLimiter::tooManyAttempts($this->identityThrottleKey($request, $login), $identityMaxAttempts);
        $ipBlocked = RateLimiter::tooManyAttempts($this->ipThrottleKey($request), $ipMaxAttempts);

        if (! $identityBlocked && ! $ipBlocked) {
            return;
        }

        $seconds = max(
            RateLimiter::availableIn($this->identityThrottleKey($request, $login)),
            RateLimiter::availableIn($this->ipThrottleKey($request)),
            $identityDecaySeconds,
            $ipDecaySeconds,
        );

        throw new HttpResponseException(
            ApiResponse::error(
                request: $request,
                code: 'AUTH_RATE_LIMITED',
                message: trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
                status: 429,
                details: ['retry_after_seconds' => $seconds],
            )
        );
    }

    private function hitRateLimiters(Request $request, string $login): void
    {
        RateLimiter::hit(
            $this->identityThrottleKey($request, $login),
            (int) config('auth.login_rate_limit.decay_seconds_per_identity', 60)
        );
        RateLimiter::hit(
            $this->ipThrottleKey($request),
            (int) config('auth.login_rate_limit.decay_seconds_per_ip', 60)
        );
    }

    private function clearRateLimiters(Request $request, string $login): void
    {
        RateLimiter::clear($this->identityThrottleKey($request, $login));
        RateLimiter::clear($this->ipThrottleKey($request));
    }

    private function identityThrottleKey(Request $request, string $login): string
    {
        return 'api-login:identity:'.Str::transliterate(Str::lower($login)).'|'.$request->ip();
    }

    private function ipThrottleKey(Request $request): string
    {
        return 'api-login:ip:'.$request->ip();
    }
}
