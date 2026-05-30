<?php

namespace App\Console\Commands;

use App\Services\Mail\TransactionalMailStatsSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class TransactionalMailStatsSyncCommand extends Command
{
    protected $signature = 'mail:stats:sync
        {--provider=kinghost_smtp}
        {--start= : Data inicial YYYY-MM-DD}
        {--end= : Data final YYYY-MM-DD}';

    protected $description = 'Sincroniza métricas de e-mail transacional da API para snapshots locais.';

    public function handle(TransactionalMailStatsSyncService $syncService): int
    {
        $provider = (string) $this->option('provider');

        try {
            $start = $this->option('start') ? Carbon::parse((string) $this->option('start'))->startOfDay() : null;
            $end = $this->option('end') ? Carbon::parse((string) $this->option('end'))->endOfDay() : null;

            $result = $syncService->syncRange($start, $end, $provider);

            $this->info('Sincronização concluída.');
            $this->line('Provider: '.$provider);
            $this->line('Período: '.$result['start_date'].' até '.$result['end_date']);
            $this->line('Snapshots atualizados: '.(int) $result['snapshots_upserted']);
            $this->line('Mensagens sincronizadas: '.(int) ($result['messages_upserted'] ?? 0));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Falha ao sincronizar métricas: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
