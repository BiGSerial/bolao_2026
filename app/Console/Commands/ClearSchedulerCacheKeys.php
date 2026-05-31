<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearSchedulerCacheKeys extends Command
{
    protected $signature   = 'scheduler:clear-cache';
    protected $description = 'Remove chaves de cache do smart-sync-orchestrator (força re-execução imediata)';

    public function handle(): int
    {
        $redis = Cache::getRedis();

        $removed = [];
        foreach (['*scheduler:af*', '*scheduler:fd*'] as $pattern) {
            foreach ($redis->keys($pattern) as $key) {
                $redis->del($key);
                $removed[] = $key;
            }
        }

        if (empty($removed)) {
            $this->info('Nenhuma chave encontrada.');
            return self::SUCCESS;
        }

        $this->info('Chaves removidas (' . count($removed) . '):');
        foreach ($removed as $key) {
            $this->line("  - $key");
        }

        return self::SUCCESS;
    }
}
