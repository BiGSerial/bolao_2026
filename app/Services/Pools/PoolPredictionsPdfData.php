<?php

namespace App\Services\Pools;

use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PoolPredictionsPdfData
{
    public function __construct(
        private readonly LivePoolRankingService $livePoolRankingService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Pool $pool, User $user): array
    {
        $pool->loadMissing([
            'competition:id,code,name',
            'season:id,year',
            'owner:id,name,display_name',
        ]);

        $leaders = $pool->members()
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'manager'])
            ->with('user:id,name,display_name')
            ->orderByRaw("case role when 'owner' then 0 else 1 end")
            ->orderBy('id')
            ->get()
            ->map(fn ($member): array => [
                'role' => $member->role === 'owner' ? 'Organizador' : 'Gestor',
                'name' => (string) ($member->user?->public_name ?? '—'),
            ])
            ->values();

        if (! $leaders->contains(fn (array $leader): bool => $leader['role'] === 'Organizador')) {
            $leaders->prepend([
                'role' => 'Organizador',
                'name' => (string) ($pool->owner?->public_name ?? '—'),
            ]);
        }

        $ranking = $this->livePoolRankingService
            ->build($pool)
            ->firstWhere('user_id', (int) $user->id);

        $matches = FootballMatch::query()
            ->where('competition_id', $pool->competition_id)
            ->where('competition_season_id', $pool->competition_season_id)
            ->when($pool->stage, fn ($query) => $query->where('stage', $pool->stage))
            ->with([
                'homeTeam:id,name,canonical_name_br,short_name,tla',
                'awayTeam:id,name,canonical_name_br,short_name,tla',
            ])
            ->orderBy('utc_date')
            ->orderBy('id')
            ->get();

        $predictions = Prediction::query()
            ->where('pool_id', $pool->id)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('football_match_id');

        return [
            'app_name' => (string) config('app.name', 'BolãoVF'),
            'logo' => $this->imageDataUri(public_path('img/logo.png')),
            'pool' => $pool,
            'participant' => [
                'name' => (string) $user->public_name,
                'position' => $ranking?->position,
                'points' => (int) ($ranking?->points_total ?? 0),
            ],
            'leaders' => $leaders,
            'rules' => $this->rules($pool),
            'tie_breakers' => $this->tieBreakers($pool->tie_breakers ?? []),
            'matches' => $matches->map(fn (FootballMatch $match): array => $this->matchRow(
                $match,
                $predictions->get($match->id),
            )),
            'generated_at' => now(config('app.timezone')),
            'filename' => Str::slug("{$pool->name}-palpites-{$user->public_name}").'.pdf',
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function rules(Pool $pool): Collection
    {
        $goalRule = $pool->correct_goals_mode === 'winner_only'
            ? 'apenas para os gols do time vencedor'
            : 'quando acertar os gols de pelo menos um dos times';

        return collect([
            "Placar exato: {$pool->points_exact_score} ponto(s).",
            "Resultado correto (vitória ou empate): {$pool->points_correct_result} ponto(s).",
            "Gols corretos de um time: {$pool->points_correct_goals} ponto(s), {$goalRule}.",
            $pool->closed_predictions
                ? 'Palpite único: alterações encerram no bloqueio do primeiro jogo.'
                : "Palpites bloqueiam {$pool->prediction_lock_minutes} minuto(s) antes de cada partida.",
            $pool->allow_prediction_changes
                ? 'Alterações são permitidas até o horário de bloqueio.'
                : 'Depois de salvo, o palpite não pode ser alterado.',
        ]);
    }

    /**
     * @param  array<int, string>  $tieBreakers
     * @return Collection<int, string>
     */
    private function tieBreakers(array $tieBreakers): Collection
    {
        $labels = [
            'exact_scores' => 'Mais placares exatos',
            'correct_results' => 'Mais resultados corretos',
            'correct_home_goals' => 'Mais gols do mandante corretos',
            'correct_away_goals' => 'Mais gols do visitante corretos',
            'predictions_counted' => 'Mais palpites válidos',
        ];

        return collect($tieBreakers)
            ->map(fn (string $criterion): string => $labels[$criterion] ?? $criterion)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function matchRow(FootballMatch $match, ?Prediction $prediction): array
    {
        $finished = in_array((string) $match->status, ['FINISHED', 'AWARDED'], true)
            && $match->home_score_full_time !== null
            && $match->away_score_full_time !== null;

        return [
            'date' => $match->kickoffAtBrazil()?->format('d/m/Y H:i') ?? '—',
            'stage' => $this->stageLabel((string) ($match->stage ?? '')),
            'round' => $match->group_name
                ? $this->groupLabel((string) $match->group_name)
                : ($match->matchday ? 'Rodada '.$match->matchday : ''),
            'home_team' => (string) ($match->homeTeam?->localized_name ?? 'A definir'),
            'away_team' => (string) ($match->awayTeam?->localized_name ?? 'A definir'),
            'prediction' => $prediction
                ? "{$prediction->home_score} x {$prediction->away_score}"
                : '—',
            'result' => $finished
                ? "{$match->home_score_full_time} x {$match->away_score_full_time}"
                : '____ x ____',
            'points' => $finished
                ? ($prediction ? (string) ((int) $prediction->points) : '—')
                : '____',
            'finished' => $finished,
        ];
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            'GROUP_STAGE' => 'Fase de grupos',
            'LAST_16', 'ROUND_OF_16' => 'Oitavas de final',
            'QUARTER_FINALS' => 'Quartas de final',
            'SEMI_FINALS' => 'Semifinal',
            'THIRD_PLACE' => 'Terceiro lugar',
            'FINAL' => 'Final',
            'REGULAR_SEASON' => 'Temporada regular',
            default => Str::headline(strtolower($stage)),
        };
    }

    private function groupLabel(string $group): string
    {
        if (preg_match('/^(?:GROUP|GRUPO)[ _-]*([A-Z0-9]+)$/i', trim($group), $matches) === 1) {
            return 'Grupo '.strtoupper($matches[1]);
        }

        return $group;
    }

    private function imageDataUri(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';
        if (! extension_loaded('gd') && $mime !== 'image/svg+xml') {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }
}
