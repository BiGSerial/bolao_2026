<?php

namespace App\Console\Commands;

use App\Services\FootballData\SyncWorldCupMatchDetailsService;
use Illuminate\Console\Command;

class SyncWorldCupMatchDetails extends Command
{
    protected $signature = 'worldcup:sync-match-details {--limit=8 : Quantidade máxima de jogos por execução}';
    protected $description = 'Sincroniza detalhes de partidas da Copa 2026 em lote, com cache em banco.';

    public function handle(SyncWorldCupMatchDetailsService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $result = $service->syncBatch($limit);

        $this->info(sprintf(
            'Detalhes sincronizados. Selecionados: %d | Atualizados: %d | Erros: %d',
            $result['selected'],
            $result['updated'],
            $result['errors']
        ));

        return self::SUCCESS;
    }
}

