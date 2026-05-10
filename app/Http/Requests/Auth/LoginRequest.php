<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const IP_LIMITER_PREFIX = 'login:ip:';
    private const IDENTITY_LIMITER_PREFIX = 'login:identity:';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $this->hitRateLimiters();

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if (Auth::user()?->status !== 'active') {
            Auth::logout();
            $this->hitRateLimiters();

            throw ValidationException::withMessages([
                'email' => 'Sua conta está sem acesso à plataforma. Contate o administrador.',
            ]);
        }

        $this->clearRateLimiters();
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $identityMaxAttempts = (int) config('auth.login_rate_limit.max_attempts_per_identity', 5);
        $identityDecaySeconds = (int) config('auth.login_rate_limit.decay_seconds_per_identity', 60);
        $ipMaxAttempts = (int) config('auth.login_rate_limit.max_attempts_per_ip', 25);
        $ipDecaySeconds = (int) config('auth.login_rate_limit.decay_seconds_per_ip', 60);

        $identityBlocked = RateLimiter::tooManyAttempts($this->identityThrottleKey(), $identityMaxAttempts);
        $ipBlocked = RateLimiter::tooManyAttempts($this->ipThrottleKey(), $ipMaxAttempts);

        if (! $identityBlocked && ! $ipBlocked) {
            return;
        }

        event(new Lockout($this));

        $identitySeconds = RateLimiter::availableIn($this->identityThrottleKey());
        $ipSeconds = RateLimiter::availableIn($this->ipThrottleKey());
        $seconds = max($identitySeconds, $ipSeconds, $identityDecaySeconds, $ipDecaySeconds);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return $this->identityThrottleKey();
    }

    private function hitRateLimiters(): void
    {
        $identityDecaySeconds = (int) config('auth.login_rate_limit.decay_seconds_per_identity', 60);
        $ipDecaySeconds = (int) config('auth.login_rate_limit.decay_seconds_per_ip', 60);

        RateLimiter::hit($this->identityThrottleKey(), $identityDecaySeconds);
        RateLimiter::hit($this->ipThrottleKey(), $ipDecaySeconds);
    }

    private function clearRateLimiters(): void
    {
        RateLimiter::clear($this->identityThrottleKey());
        RateLimiter::clear($this->ipThrottleKey());
    }

    private function identityThrottleKey(): string
    {
        $identity = Str::transliterate(Str::lower($this->string('email')->toString()));

        return self::IDENTITY_LIMITER_PREFIX.$identity.'|'.$this->ip();
    }

    private function ipThrottleKey(): string
    {
        return self::IP_LIMITER_PREFIX.$this->ip();
    }
}
