<?php

namespace App\Console\Commands;

use App\Services\Mail\TransactionalMailStatsService;
use Illuminate\Console\Command;

class TransactionalMailStatsCommand extends Command
{
    protected $signature = 'mail:stats {--provider=kinghost_smtp}';

    protected $description = 'Mostra estatísticas de envios transacionais (consumo mensal e saldo).';

    public function handle(TransactionalMailStatsService $statsService): int
    {
        $provider = (string) $this->option('provider');
        $stats = $statsService->summary($provider);

        $this->info('Estatísticas de e-mail transacional');
        $this->line('Provider: '.$stats['provider']);
        $this->line('Enviados hoje: '.$stats['sent_today']);
        $this->line('Falhas hoje: '.$stats['failed_today']);
        $this->line('Enviados no mês: '.$stats['sent_this_month']);
        $this->line('Falhas no mês: '.$stats['failed_this_month']);
        $this->line('Limite mensal: '.($stats['monthly_limit'] > 0 ? (string) $stats['monthly_limit'] : 'não configurado'));
        $this->line('Saldo mensal: '.($stats['remaining_this_month'] !== null ? (string) $stats['remaining_this_month'] : 'não aplicável'));

        return self::SUCCESS;
    }
}

