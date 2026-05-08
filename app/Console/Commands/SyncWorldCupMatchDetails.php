<?php

namespace App\Console\Commands;

use App\Services\FootballData\FootballDataClient;
use App\Services\FootballData\SyncWorldCupStandingsService;
use App\Services\FootballData\SyncWorldCupMatchDetailsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncWorldCupMatchDetails extends Command
{
    protected $signature = 'worldcup:sync-match-details {--limit=8 : Quantidade máxima de jogos por execução}';
    protected $description = 'Sincroniza detalhes de partidas da Copa 2026 em lote, com cache em banco.';

    public function handle(
        SyncWorldCupMatchDetailsService $service,
        FootballDataClient $client,
        SyncWorldCupStandingsService $standingsSyncService
    ): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $result = $service->syncBatch($limit);
        $standingsInfo = $this->syncStandingsIfDue($client, $standingsSyncService);

        $this->info(sprintf(
            'Detalhes sincronizados. Selecionados: %d | Atualizados: %d | Erros: %d | Standings: %s',
            $result['selected'],
            $result['updated'],
            $result['errors'],
            $standingsInfo
        ));

        return self::SUCCESS;
    }

    private function syncStandingsIfDue(
        FootballDataClient $client,
        SyncWorldCupStandingsService $standingsSyncService
    ): string {
        $cooldownMinutes = 5;
        $cacheKey = 'worldcup:standings:last_sync_at';
        $lastSyncAt = Cache::get($cacheKey);

        if ($lastSyncAt && now()->diffInMinutes($lastSyncAt) < $cooldownMinutes) {
            return 'ignorado (cooldown)';
        }

        try {
            $payload = $client->worldCupStandings();
            $groups = $standingsSyncService->sync($payload);
            Cache::put($cacheKey, now(), now()->addMinutes($cooldownMinutes + 1));

            return $groups.' grupo(s)';
        } catch (Throwable $e) {
            report($e);

            return 'falha';
        }
    }
}
