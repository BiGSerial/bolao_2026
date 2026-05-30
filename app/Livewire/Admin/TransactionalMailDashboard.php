<?php

namespace App\Livewire\Admin;

use App\Models\TransactionalMailLog;
use App\Models\TransactionalMailMetricSnapshot;
use App\Services\Mail\TransactionalMailStatsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionalMailDashboard extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->assertAdmin();
    }

    private function assertAdmin(): void
    {
        abort_unless((bool) Auth::user()?->is_admin, 403);
    }

    public function render(TransactionalMailStatsService $statsService)
    {
        $this->assertAdmin();

        $stats = $statsService->summary('kinghost_smtp');

        $logs = TransactionalMailLog::query()
            ->where('provider', 'kinghost_smtp')
            ->orderByDesc('created_at')
            ->paginate(20);

        $start = Carbon::now()->startOfDay()->subDays(6);

        $weeklyMap = [];
        for ($i = 0; $i < 7; $i += 1) {
            $d = $start->copy()->addDays($i);
            $key = $d->format('Y-m-d');
            $weeklyMap[$key] = [
                'label' => $d->format('d/m'),
                'sent' => 0,
                'failed' => 0,
            ];
        }

        $snapshotRows = TransactionalMailMetricSnapshot::query()
            ->where('provider', 'kinghost_smtp')
            ->where('period_type', 'daily')
            ->whereBetween('reference_date', [$start->toDateString(), now()->toDateString()])
            ->orderBy('reference_date')
            ->get();

        if ($snapshotRows->isNotEmpty()) {
            foreach ($snapshotRows as $row) {
                $key = $row->reference_date?->toDateString();
                if (! $key || ! isset($weeklyMap[$key])) continue;
                $weeklyMap[$key]['sent'] = (int) ($row->messages ?? 0);
                $weeklyMap[$key]['failed'] = (int) (($row->bounces ?? 0) + ($row->hard_bounces ?? 0));
            }
        } else {
            $rows = TransactionalMailLog::query()
                ->where('provider', 'kinghost_smtp')
                ->whereIn('status', ['sent', 'failed'])
                ->whereRaw('COALESCE(sent_at, created_at) >= ?', [$start])
                ->selectRaw('DATE(COALESCE(sent_at, created_at)) as day_ref, status, COUNT(*) as total')
                ->groupBy('day_ref', 'status')
                ->get();

            foreach ($rows as $row) {
                $key = (string) $row->day_ref;
                if (! isset($weeklyMap[$key])) continue;
                if ($row->status === 'sent') $weeklyMap[$key]['sent'] = (int) $row->total;
                if ($row->status === 'failed') $weeklyMap[$key]['failed'] = (int) $row->total;
            }
        }

        $weeklySeries = array_values($weeklyMap);

        return view('livewire.admin.transactional-mail-dashboard', [
            'stats' => $stats,
            'logs' => $logs,
            'weeklySeries' => $weeklySeries,
        ]);
    }
}
