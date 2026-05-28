<?php

namespace App\Services\Mail;

use App\Models\TransactionalMailLog;
use Illuminate\Support\Carbon;

class TransactionalMailStatsService
{
    /**
     * @return array{
     *   provider:string,
     *   monthly_limit:int,
     *   sent_this_month:int,
     *   failed_this_month:int,
     *   sent_today:int,
     *   failed_today:int,
     *   remaining_this_month:int|null
     * }
     */
    public function summary(string $provider = 'kinghost_smtp'): array
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $todayStart = Carbon::now()->startOfDay();
        $todayEnd = Carbon::now()->endOfDay();

        $base = TransactionalMailLog::query()->where('provider', $provider);

        $sentThisMonth = (clone $base)
            ->where('status', 'sent')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $failedThisMonth = (clone $base)
            ->where('status', 'failed')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $sentToday = (clone $base)
            ->where('status', 'sent')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $failedToday = (clone $base)
            ->where('status', 'failed')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $monthlyLimit = (int) config('services.kinghost_smtp.monthly_limit', 0);
        $remaining = $monthlyLimit > 0 ? max(0, $monthlyLimit - $sentThisMonth) : null;

        return [
            'provider' => $provider,
            'monthly_limit' => $monthlyLimit,
            'sent_this_month' => $sentThisMonth,
            'failed_this_month' => $failedThisMonth,
            'sent_today' => $sentToday,
            'failed_today' => $failedToday,
            'remaining_this_month' => $remaining,
        ];
    }
}

