<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class EmailDispatchGuard
{
    public function attempt(string $scope, string $identity, int $maxAttempts, int $decaySeconds): bool
    {
        if (! $this->hasMonthlyQuota()) {
            return false;
        }

        $key = 'mail-rate:'.$scope.':'.sha1($identity);
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        RateLimiter::hit($key, $decaySeconds);
        $this->consumeMonthly();

        return true;
    }

    public function remainingMonthly(): int
    {
        $limit = (int) config('email-limits.monthly_quota', 10000);
        $consumed = (int) Cache::get($this->monthlyKey(), 0);

        return max(0, $limit - $consumed);
    }

    public function hasMonthlyQuota(): bool
    {
        return $this->remainingMonthly() > 0;
    }

    private function consumeMonthly(): void
    {
        $key = $this->monthlyKey();
        Cache::add($key, 0, now()->endOfMonth());
        Cache::increment($key);
    }

    private function monthlyKey(): string
    {
        return 'mail-quota:'.now()->format('Ym');
    }
}

