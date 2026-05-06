<?php

namespace App\Livewire\Admin;

use App\Jobs\CalculatePredictionsForMatchJob;
use App\Jobs\RecalculatePoolRankingsJob;
use App\Models\ApiSyncLog;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Services\FootballData\FootballDataClient;
use App\Services\FootballData\SyncWorldCupMatchesService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class ApiSyncDashboard extends Component
{
    use WithPagination;

    public bool $syncing = false;
    public string $syncMessage = '';
    public bool $syncSuccess = false;

    public function mount(): void
    {
        $this->assertAdmin();
    }

    public function triggerSync(FootballDataClient $client, SyncWorldCupMatchesService $syncService): void
    {
        $this->assertAdmin();

        $this->syncing = true;
        $this->syncMessage = '';

        try {
            $payload = $client->worldCupGroupStageMatches();
            $changed = $syncService->sync($payload);

            foreach ($changed as $match) {
                if ($match->status === 'FINISHED') {
                    CalculatePredictionsForMatchJob::dispatch($match->id);
                }
            }

            if ($changed->isNotEmpty()) {
                Pool::query()->pluck('id')->each(fn (int $id) => RecalculatePoolRankingsJob::dispatch($id));
            }

            ApiSyncLog::create([
                'provider' => 'football_data',
                'endpoint' => '/competitions/WC/matches',
                'http_status' => 200,
                'success' => true,
                'records_total' => count($payload['matches'] ?? []),
                'records_changed' => $changed->count(),
                'message' => 'Sync manual via painel admin.',
                'meta' => ['stage' => config('football-data.world_cup.stage')],
                'synced_at' => now(),
            ]);

            $this->syncSuccess = true;
            $this->syncMessage = "Sync concluído. {$changed->count()} jogo(s) alterado(s) de ".count($payload['matches'] ?? []).' total.';
        } catch (Throwable $e) {
            ApiSyncLog::create([
                'provider' => 'football_data',
                'endpoint' => '/competitions/WC/matches',
                'success' => false,
                'message' => 'Falha na sincronizacao manual via painel admin.',
                'meta' => ['exception' => get_class($e)],
                'synced_at' => now(),
            ]);

            $this->syncSuccess = false;
            $this->syncMessage = 'Erro ao sincronizar. Verifique token/configuracoes e tente novamente.';
        } finally {
            $this->syncing = false;
        }
    }

    public function render()
    {
        $this->assertAdmin();

        $logs = ApiSyncLog::query()
            ->orderByDesc('synced_at')
            ->paginate(15);

        $lastSuccess = ApiSyncLog::query()
            ->where('success', true)
            ->orderByDesc('synced_at')
            ->first();

        $totalMatches = FootballMatch::count();
        $liveMatches  = FootballMatch::whereIn('status', ['IN_PLAY', 'PAUSED'])->count();
        $totalSyncs   = ApiSyncLog::count();
        $failedSyncs  = ApiSyncLog::where('success', false)
            ->where('synced_at', '>=', now()->subDay())
            ->count();

        return view('livewire.admin.apisyncdashboard', compact(
            'logs', 'lastSuccess', 'totalMatches', 'liveMatches', 'totalSyncs', 'failedSyncs'
        ));
    }

    private function assertAdmin(): void
    {
        abort_unless((bool) Auth::user()?->is_admin, 403);
    }
}
