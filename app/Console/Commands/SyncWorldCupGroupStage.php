<?php

namespace App\Console\Commands;

use App\Jobs\CalculatePredictionsForMatchJob;
use App\Jobs\RecalculatePoolRankingsJob;
use App\Models\ApiSyncLog;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Services\FootballData\FootballDataClient;
use App\Services\FootballData\SyncWorldCupMatchesService;
use App\Services\FootballData\SyncWorldCupStandingsService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Throwable;

class SyncWorldCupGroupStage extends Command
{
    protected $signature = 'worldcup:sync-group-stage {--force : Ignora janela inteligente e sincroniza agora}';
    protected $description = 'Sincroniza jogos e placares da fase de grupos da Copa 2026.';

    public function handle(
        FootballDataClient $client,
        SyncWorldCupMatchesService $syncService,
        SyncWorldCupStandingsService $standingsSyncService
    ): int
    {
        if (! $this->option('force') && ! $this->shouldSyncNow()) {
            ApiSyncLog::create([
                'provider' => 'football_data',
                'endpoint' => '/competitions/WC/matches',
                'success' => true,
                'message' => 'Sync ignorado: fora da janela de sincronizacao.',
                'meta' => ['status' => 'skipped', 'stage' => config('football-data.world_cup.stage')],
                'synced_at' => now(),
            ]);

            $this->info('Fora da janela de sincronizacao.');
            return self::SUCCESS;
        }

        try {
            $payload = $client->worldCupGroupStageMatches();
            $changed = $syncService->sync($payload);
            $standingsPayload = $client->worldCupStandings();
            $standingsCount = $standingsSyncService->sync($standingsPayload);

            foreach ($changed as $match) {
                if ($match->status === 'FINISHED') {
                    CalculatePredictionsForMatchJob::dispatch($match->id);
                }
            }

            if ($changed->isNotEmpty()) {
                Pool::query()->pluck('id')->each(fn (int $poolId) => RecalculatePoolRankingsJob::dispatch($poolId));
            }

            ApiSyncLog::create([
                'provider' => 'football_data',
                'endpoint' => '/competitions/WC/matches',
                'http_status' => 200,
                'success' => true,
                'records_total' => count($payload['matches'] ?? []),
                'records_changed' => $changed->count(),
                'message' => 'Sync executado com sucesso.',
                'meta' => [
                    'status' => 'success',
                    'stage' => config('football-data.world_cup.stage'),
                    'result_set' => data_get($payload, 'resultSet'),
                    'standings_synced' => $standingsCount,
                ],
                'synced_at' => now(),
            ]);

            $this->info('Sincronizacao concluida. Jogos alterados: '.$changed->count().' | Grupos sincronizados: '.$standingsCount);
            return self::SUCCESS;
        } catch (Throwable $e) {
            $httpStatus = $e instanceof RequestException ? $e->response?->status() : null;

            ApiSyncLog::create([
                'provider' => 'football_data',
                'endpoint' => '/competitions/WC/matches',
                'http_status' => $httpStatus,
                'success' => false,
                'message' => $e->getMessage(),
                'meta' => [
                    'status' => $httpStatus === 429 ? 'rate_limited' : 'failed',
                    'exception' => get_class($e),
                ],
                'synced_at' => now(),
            ]);
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function shouldSyncNow(): bool
    {
        $baseQuery = FootballMatch::query()
            ->where('stage', config('football-data.world_cup.stage'));

        // Primeira carga: se ainda não existe nenhum jogo da fase no banco,
        // precisa sincronizar para popular datas/partidas.
        if (! $baseQuery->exists()) {
            return true;
        }

        return (clone $baseQuery)
            ->whereBetween('utc_date', [
                now()->utc()->subHours(1),
                now()->utc()->addHours(4),
            ])
            ->whereIn('status', ['TIMED', 'SCHEDULED', 'IN_PLAY', 'PAUSED'])
            ->exists();
    }
}
