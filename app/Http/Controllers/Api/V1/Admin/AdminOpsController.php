<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiSyncLog;
use App\Models\TransactionalMailLog;
use App\Models\TransactionalMailMetricSnapshot;
use App\Services\Mail\TransactionalMailStatsService;
use App\Support\ApiResponse;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class AdminOpsController extends Controller
{
    public function syncStatus(Request $request): JsonResponse
    {
        $request->user()->can('admin') || abort(403);

        $last = ApiSyncLog::query()
            ->select(['id', 'provider', 'success', 'synced_at'])
            ->latest('id')
            ->first();
        $ok24h = ApiSyncLog::query()->where('success', true)->where('synced_at', '>=', now()->subDay())->count();
        $err24h = ApiSyncLog::query()->where('success', false)->where('synced_at', '>=', now()->subDay())->count();

        $requestLogs24h = ApiSyncLog::query()
            ->where('is_request_log', true)
            ->where('synced_at', '>=', now()->subDay())
            ->select(['provider', 'success', 'duration_ms', 'synced_at'])
            ->get();

        $since = now()->subHours(23)->startOfHour();
        $hourlyRaw = ApiSyncLog::query()
            ->where('synced_at', '>=', $since)
            ->where('is_request_log', false)
            ->selectRaw("DATE_FORMAT(synced_at, '%Y-%m-%d %H:00:00') as hour_slot, COUNT(*) as total, SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as ok")
            ->groupBy('hour_slot')
            ->orderBy('hour_slot')
            ->get()
            ->keyBy('hour_slot');
        $hourly = [];
        for ($h = 0; $h < 24; $h++) {
            $slot = $since->copy()->addHours($h);
            $key = $slot->format('Y-m-d H:00:00');
            $row = $hourlyRaw->get($key);
            $hourly[] = [
                'label' => $slot->format('H:i'),
                'total' => (int) ($row->total ?? 0),
                'ok' => (int) ($row->ok ?? 0),
                'error' => max(0, (int) (($row->total ?? 0) - ($row->ok ?? 0))),
            ];
        }

        $providers = ['football_data', 'api_football'];
        $byApi = [];
        foreach ($providers as $provider) {
            $rows = $requestLogs24h->where('provider', $provider);
            $total = $rows->count();
            $ok = $rows->where('success', true)->count();
            $failed = max(0, $total - $ok);
            $avgLatency = round((float) ($rows->whereNotNull('duration_ms')->avg('duration_ms') ?? 0), 1);
            $peakRow = ApiSyncLog::query()
                ->where('is_request_log', true)
                ->where('provider', $provider)
                ->where('synced_at', '>=', now()->subDay())
                ->selectRaw("DATE_FORMAT(synced_at, '%Y-%m-%d %H:%i:00') as minute_slot, COUNT(*) as c")
                ->groupBy('minute_slot')
                ->orderByDesc('c')
                ->orderByDesc('minute_slot')
                ->first();

            $byApi[] = [
                'api' => $provider,
                'total_24h' => $total,
                'ok_24h' => $ok,
                'failed_24h' => $failed,
                'avg_latency_ms' => $avgLatency,
                'peak_rpm' => (int) ($peakRow->c ?? 0),
                'peak_minute' => (string) ($peakRow->minute_slot ?? '—'),
            ];
        }

        $hours = [];
        $labels = [];
        for ($i = 23; $i >= 0; $i--) {
            $slot = now()->subHours($i)->startOfHour();
            $hours[] = $slot->format('Y-m-d H:00:00');
            $labels[] = $slot->format('H:i');
        }

        $apiSeries = [];
        foreach ($providers as $provider) {
            $apiSeries[$provider] = array_fill(0, count($hours), 0);
        }
        foreach ($requestLogs24h as $log) {
            $slot = $log->synced_at?->copy()->startOfHour();
            if (! $slot) {
                continue;
            }

            $key = $slot->format('Y-m-d H:00:00');
            $index = array_search($key, $hours, true);
            $provider = (string) ($log->provider ?? '');
            if ($index === false || ! isset($apiSeries[$provider])) {
                continue;
            }
            $apiSeries[$provider][$index] += 1;
        }

        $hourlyByApi = [];
        foreach ($apiSeries as $provider => $values) {
            $hourlyByApi[] = ['api' => $provider, 'values' => $values];
        }

        return ApiResponse::success($request, [
            'last_sync' => $last?->synced_at?->toIso8601String(),
            'last_provider' => $last?->provider,
            'last_success' => (bool) ($last?->success ?? false),
            'success_24h' => $ok24h,
            'errors_24h' => $err24h,
            'hourly_24h' => $hourly,
            'hourly_by_api_24h' => [
                'labels' => $labels,
                'series' => $hourlyByApi,
            ],
            'activity_by_api_24h' => $byApi,
            'recent' => ApiSyncLog::query()->latest('synced_at')->limit(20)->get(['id', 'provider', 'success', 'meta', 'synced_at']),
        ]);
    }

    public function runSync(Request $request): JsonResponse
    {
        $request->user()->can('admin') || abort(403);

        $validated = $request->validate([
            'command' => ['required', 'string', 'in:base,details,consolidate'],
            'limit' => ['nullable', 'integer', 'min:10', 'max:500'],
        ]);

        $output = '';
        $exitCode = 1;

        if ($validated['command'] === 'base') {
            $exitCode = Artisan::call('worldcup:sync-group-stage', []);
            $output = Artisan::output();
        } elseif ($validated['command'] === 'details') {
            $exitCode = Artisan::call('worldcup:sync-match-details', [
                '--limit' => (int) ($validated['limit'] ?? 120),
                '--sync-type' => 'manual_admin',
            ]);
            $output = Artisan::output();
        } elseif ($validated['command'] === 'consolidate') {
            $exitCode = Artisan::call('sports:consolidate-daily-results', [
                '--timezone' => config('app.timezone', 'America/Sao_Paulo'),
            ]);
            $output = Artisan::output();
        }

        return ApiResponse::success($request, [
            'exit_code' => $exitCode,
            'ok' => $exitCode === 0,
            'command' => $validated['command'],
            'output' => trim($output),
        ]);
    }

    public function emailStatus(Request $request, TransactionalMailStatsService $statsService): JsonResponse
    {
        $request->user()->can('admin') || abort(403);

        $provider = (string) $request->query('provider', 'kinghost_smtp');
        $summary = $statsService->summary($provider);
        $lastMail = TransactionalMailLog::query()
            ->where('provider', $provider)
            ->latest('created_at')
            ->first();
        $recentMails = TransactionalMailLog::query()
            ->where('provider', $provider)
            ->latest('created_at')
            ->limit(30)
            ->get(['id', 'to_address', 'subject', 'status', 'created_at', 'sent_at', 'failed_at']);

        $startDay = Carbon::now()->subDays(6)->startOfDay();
        $dailyRaw = TransactionalMailMetricSnapshot::query()
            ->where('provider', $provider)
            ->where('period_type', 'daily')
            ->whereDate('reference_date', '>=', $startDay->toDateString())
            ->orderBy('reference_date')
            ->get(['reference_date', 'messages', 'bounces'])
            ->keyBy(fn ($row) => Carbon::parse($row->reference_date)->format('Y-m-d'));
        $daily = [];
        for ($d = 0; $d < 7; $d++) {
            $day = $startDay->copy()->addDays($d);
            $key = $day->format('Y-m-d');
            $row = $dailyRaw->get($key);
            $daily[] = [
                'label' => $day->format('d/m'),
                'messages' => (int) ($row->messages ?? 0),
                'bounces' => (int) ($row->bounces ?? 0),
            ];
        }

        return ApiResponse::success($request, [
            'summary' => $summary,
            'last_mail_at' => $lastMail?->created_at?->toIso8601String(),
            'daily_7d' => $daily,
            'recent_mails' => $recentMails,
        ]);
    }

    public function runEmailSync(Request $request): JsonResponse
    {
        $request->user()->can('admin') || abort(403);

        $provider = (string) $request->input('provider', 'kinghost_smtp');
        $exitCode = Artisan::call('mail:stats:sync', ['--provider' => $provider]);

        return ApiResponse::success($request, [
            'exit_code' => $exitCode,
            'ok' => $exitCode === 0,
            'output' => trim(Artisan::output()),
        ]);
    }
}
