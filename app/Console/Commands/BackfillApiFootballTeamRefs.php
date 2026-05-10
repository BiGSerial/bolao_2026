<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\TeamProviderRef;
use App\Services\ApiFootball\ApiFootballClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BackfillApiFootballTeamRefs extends Command
{
    protected $signature = 'api-football:backfill-team-refs
        {--code= : Codigo da competicao (ex: BSA, WC)}
        {--season= : Temporada (ex: 2026)}
        {--dry-run : Apenas simula, sem persistir}
        {--force : Atualiza refs existentes de api_football quando divergir}';

    protected $description = 'Cria/atualiza team_provider_refs (api_football) a partir de match por nome canonico.';

    public function handle(ApiFootballClient $client): int
    {
        $contexts = $this->contexts();
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($contexts === []) {
            $this->warn('Nenhum contexto de competicao configurado para processar.');

            return self::SUCCESS;
        }

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;
        $totalAmbiguous = 0;

        foreach ($contexts as $ctx) {
            $code = $ctx['code'];
            $season = $ctx['season'];
            $leagueId = $ctx['league_id'];

            $this->line(sprintf('Processando %s/%d (league_id=%d)%s', $code, $season, $leagueId, $dryRun ? ' [dry-run]' : ''));

            $localTeams = $this->competitionTeams($code, $season);
            if ($localTeams->isEmpty()) {
                $this->warn("  Sem times locais para {$code}/{$season}. Pulando.");
                continue;
            }

            $payload = $client->teams($leagueId, $season);
            $apiTeams = collect((array) data_get($payload, 'response', []));
            if ($apiTeams->isEmpty()) {
                $this->warn("  API-Football retornou 0 times para {$code}/{$season}.");
                continue;
            }

            foreach ($apiTeams as $apiTeamRow) {
                $apiId = (int) data_get($apiTeamRow, 'team.id', 0);
                $apiName = (string) data_get($apiTeamRow, 'team.name', '');
                if ($apiId <= 0 || $apiName === '') {
                    continue;
                }

                $ref = TeamProviderRef::query()
                    ->where('provider', 'api_football')
                    ->where('external_id', $apiId)
                    ->first();

                $match = $this->resolveLocalTeam($localTeams, $apiName);
                if ($match['status'] === 'ambiguous') {
                    $totalAmbiguous++;
                    $this->warn("  Ambiguo: api_id={$apiId} {$apiName} => ".$match['label']);
                    continue;
                }

                if ($match['status'] === 'not_found') {
                    $totalSkipped++;
                    $this->warn("  Sem match: api_id={$apiId} {$apiName}");
                    continue;
                }

                /** @var Team $team */
                $team = $match['team'];

                if ($ref && (int) $ref->team_id === (int) $team->id) {
                    $totalSkipped++;
                    continue;
                }

                if ($ref && ! $force) {
                    $totalSkipped++;
                    $this->warn("  Ja mapeado (use --force p/ atualizar): api_id={$apiId} {$apiName}");
                    continue;
                }

                if (! $dryRun) {
                    TeamProviderRef::updateOrCreate(
                        ['provider' => 'api_football', 'external_id' => $apiId],
                        ['team_id' => (int) $team->id]
                    );
                }

                if ($ref) {
                    $totalUpdated++;
                    $this->info("  Atualizado: {$apiName} -> {$team->name}");
                } else {
                    $totalCreated++;
                    $this->info("  Criado: {$apiName} -> {$team->name}");
                }
            }
        }

        $this->newLine();
        $this->line('Resumo:');
        $this->line("  Criados: {$totalCreated}");
        $this->line("  Atualizados: {$totalUpdated}");
        $this->line("  Ambiguos: {$totalAmbiguous}");
        $this->line("  Pulados: {$totalSkipped}");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{code:string,season:int,league_id:int}>
     */
    private function contexts(): array
    {
        $codeOpt = strtoupper((string) $this->option('code'));
        $seasonOpt = (int) $this->option('season');
        $configured = (array) config('api-football.competitions', []);

        $items = [];
        foreach ($configured as $code => $settings) {
            $normalizedCode = strtoupper((string) $code);
            if ($codeOpt !== '' && $codeOpt !== $normalizedCode) {
                continue;
            }

            $leagueId = (int) data_get($settings, 'league_id', 0);
            $season = $seasonOpt > 0 ? $seasonOpt : (int) data_get($settings, 'season', 0);
            if ($leagueId <= 0 || $season <= 0) {
                continue;
            }

            $items[] = [
                'code' => $normalizedCode,
                'season' => $season,
                'league_id' => $leagueId,
            ];
        }

        return $items;
    }

    /**
     * @return Collection<int, Team>
     */
    private function competitionTeams(string $code, int $season): Collection
    {
        $teamIds = FootballMatch::query()
            ->whereHas('competition', fn ($q) => $q->where('code', $code))
            ->whereHas('season', fn ($q) => $q->where('year', $season))
            ->get(['home_team_id', 'away_team_id'])
            ->flatMap(fn (FootballMatch $m) => [(int) $m->home_team_id, (int) $m->away_team_id])
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        return Team::query()->whereIn('id', $teamIds)->get();
    }

    /**
     * @param Collection<int, Team> $teams
     * @return array{status:'ok'|'not_found'|'ambiguous',team?:Team,label?:string}
     */
    private function resolveLocalTeam(Collection $teams, string $apiName): array
    {
        $apiRaw = $this->normalize($apiName);
        $apiCanonical = $this->canonicalClubName($apiRaw);

        $exactCandidates = $teams->filter(function (Team $team) use ($apiRaw, $apiCanonical): bool {
            $names = array_filter([
                $team->name,
                $team->short_name,
                $team->tla,
                $team->localized_name ?? null,
            ]);

            foreach ($names as $name) {
                $raw = $this->normalize((string) $name);
                $canonical = $this->canonicalClubName($raw);

                if ($raw === $apiRaw || $canonical === $apiCanonical) {
                    return true;
                }
            }

            return false;
        })->values();

        if ($exactCandidates->count() === 1) {
            return ['status' => 'ok', 'team' => $exactCandidates->first()];
        }

        if ($exactCandidates->count() > 1) {
            return [
                'status' => 'ambiguous',
                'label' => $exactCandidates->pluck('name')->join(' | '),
            ];
        }

        $scored = $teams->map(function (Team $team) use ($apiCanonical) {
            $names = array_filter([
                $team->name,
                $team->short_name,
                $team->localized_name ?? null,
            ]);

            $best = 0.0;
            foreach ($names as $name) {
                $canonical = $this->canonicalClubName($this->normalize((string) $name));
                similar_text($apiCanonical, $canonical, $percent);
                $best = max($best, $percent);
            }

            return ['team' => $team, 'score' => $best];
        })->sortByDesc('score')->values();

        $best = $scored->first();
        $second = $scored->get(1);

        if (! $best || $best['score'] < 88.0) {
            return ['status' => 'not_found'];
        }

        if ($second && ($best['score'] - $second['score']) < 4.0) {
            return [
                'status' => 'ambiguous',
                'label' => sprintf('%s (%.1f) | %s (%.1f)', $best['team']->name, $best['score'], $second['team']->name, $second['score']),
            ];
        }

        return ['status' => 'ok', 'team' => $best['team']];
    }

    private function normalize(string $value): string
    {
        $normalized = mb_strtoupper(Str::transliterate($value));
        $normalized = preg_replace('/[^A-Z0-9]+/u', ' ', $normalized) ?? '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }

    private function canonicalClubName(string $normalizedName): string
    {
        $prefixes = [
            'CLUBE DE REGATAS',
            'CLUBE DE',
            'CLUBE DO',
            'ASSOCIACAO ATLETICA',
            'ASSOCIACAO',
            'SOCIEDADE ESPORTIVA',
            'ESPORTE CLUBE',
            'CLUBE ATLETICO',
            'SPORT CLUB',
            'FOOTBALL CLUB',
            'FUTEBOL CLUBE',
            'SE ',
            'SC ',
            'FC ',
            'EC ',
            'CR ',
            'AC ',
            'CA ',
            'AA ',
            'CD ',
        ];

        $name = ' '.$normalizedName;
        foreach ($prefixes as $prefix) {
            $pref = ' '.trim($prefix).' ';
            if (str_starts_with($name, $pref)) {
                $name = ' '.substr($name, strlen($pref));
                break;
            }
        }

        $canonical = trim(preg_replace('/\s+/', ' ', $name) ?? $normalizedName);

        $aliases = [
            'ATHLETICO PARANAENSE' => 'ATLETICO PARANAENSE',
            'ATLETICO PR' => 'ATLETICO PARANAENSE',
            'ATLETICO PARANAENSE' => 'PARANAENSE',
            'CA PARANAENSE' => 'PARANAENSE',
            'ATLETICO MINEIRO' => 'MINEIRO',
            'ATLETICO MG' => 'MINEIRO',
            'CA MINEIRO' => 'MINEIRO',
            'CLUBE DO REMO' => 'REMO',
        ];

        return $aliases[$canonical] ?? $canonical;
    }
}
