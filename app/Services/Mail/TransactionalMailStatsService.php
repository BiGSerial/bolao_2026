<?php

namespace App\Services\Mail;

use App\Models\TransactionalMailLog;
use App\Models\TransactionalMailMetricSnapshot;
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

        $todaySnapshot = TransactionalMailMetricSnapshot::query()
            ->where('provider', $provider)
            ->where('period_type', 'daily')
            ->whereDate('reference_date', $todayStart->toDateString())
            ->latest('synced_at')
            ->first();

        if ($todaySnapshot) {
            $sentToday = (int) ($todaySnapshot->messages ?? 0);
        }

        $monthSnapshots = TransactionalMailMetricSnapshot::query()
            ->where('provider', $provider)
            ->where('period_type', 'daily')
            ->whereBetween('reference_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get();

        if ($monthSnapshots->isNotEmpty()) {
            $sentThisMonth = (int) $monthSnapshots->sum('messages');
        }

        $monthlyLimit = (int) config('services.kinghost_smtp.monthly_limit', 0);
        $latestBalance = TransactionalMailMetricSnapshot::query()
            ->where('provider', $provider)
            ->where('period_type', 'monthly_balance')
            ->latest('reference_date')
            ->latest('synced_at')
            ->first();

        if ($latestBalance && is_numeric($latestBalance->total_hired)) {
            $monthlyLimit = (int) ($latestBalance->total_hired ?? 0);
        }

        $remaining = null;
        if ($latestBalance && is_numeric($latestBalance->total_available)) {
            $remaining = max(0, (int) ($latestBalance->total_available ?? 0));
        } elseif ($monthlyLimit > 0) {
            $remaining = max(0, $monthlyLimit - $sentThisMonth);
        }

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
