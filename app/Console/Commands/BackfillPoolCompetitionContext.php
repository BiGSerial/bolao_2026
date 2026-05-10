<?php

namespace App\Console\Commands;

use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Pool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPoolCompetitionContext extends Command
{
    protected $signature = 'pools:backfill-competition-context
        {--code= : Codigo da competicao (ex: WC, BSA)}
        {--season= : Ano da temporada (ex: 2026)}
        {--dry-run : Apenas exibe contagens sem aplicar alteracoes}';

    protected $description = 'Preenche competition_id e competition_season_id em pools legados';

    public function handle(): int
    {
        $defaultCode = (string) config('football-data.default_competition_code', config('football-data.world_cup.code', 'WC'));
        $defaultSeason = (int) config('football-data.competitions.'.strtoupper($defaultCode).'.season', config('football-data.world_cup.season', 2026));

        $code = strtoupper((string) ($this->option('code') ?: $defaultCode));
        $seasonYear = (int) ($this->option('season') ?: $defaultSeason);

        $competition = Competition::query()->where('code', $code)->first();
        if (! $competition) {
            $this->error("Competicao {$code} nao encontrada.");
            return self::FAILURE;
        }

        $season = CompetitionSeason::query()
            ->where('competition_id', $competition->id)
            ->where('year', $seasonYear)
            ->first();

        if (! $season) {
            $this->error("Temporada {$seasonYear} da competicao {$code} nao encontrada.");
            return self::FAILURE;
        }

        $query = Pool::query()->where(function ($q): void {
            $q->whereNull('competition_id')->orWhereNull('competition_season_id');
        });

        $pending = (clone $query)->count();
        $this->info("Pools pendentes de backfill: {$pending}");

        if ($pending === 0) {
            $this->info('Nenhum pool para atualizar.');
            return self::SUCCESS;
        }

        if ((bool) $this->option('dry-run')) {
            $this->info('Dry-run finalizado sem alteracoes.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($query, $competition, $season): void {
            $query->update([
                'competition_id' => $competition->id,
                'competition_season_id' => $season->id,
                'updated_at' => now(),
            ]);
        });

        $remaining = Pool::query()
            ->whereNull('competition_id')
            ->orWhereNull('competition_season_id')
            ->count();

        $this->info('Backfill concluido.');
        $this->info("Pools ainda pendentes: {$remaining}");

        return self::SUCCESS;
    }
}
