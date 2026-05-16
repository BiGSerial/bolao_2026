<?php

namespace App\Livewire\Pools;

use App\Enums\PoolMemberRole;
use App\Enums\PoolMemberStatus;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Pool;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class PoolCreate extends Component
{
    private const AVAILABLE_TIE_BREAKERS = [
        'exact_scores',
        'correct_results',
        'correct_home_goals',
        'correct_away_goals',
        'predictions_counted',
    ];

    public string $name = '';
    public string $description = '';
    public string $instructions = '';
    public string $visibility = 'invite_only';
    public bool $allow_prediction_changes = true;
    public int $prediction_lock_minutes = 10;
    public bool $allow_pending_member_predictions = true;
    public int $points_exact_score = 5;
    public int $points_correct_result = 3;
    public int $points_correct_goals = 1;
    public string $correct_goals_mode = 'both_teams';
    public string $competition_code = '';

    /** @var string[] */
    public array $sectors = [];
    public string $newSector = '';
    public string $sectorFeedback = '';
    public string $sectorFeedbackType = '';
    /** @var string[] */
    public array $tieBreakers = [];
    public string $newTieBreaker = '';

    public function mount(): void
    {
        $requested = strtoupper((string) request()->query('competition', session('competition', '')));
        $options = $this->availableCompetitionOptions();
        $defaultCode = strtoupper((string) config('football-data.default_competition_code', 'WC'));

        if ($requested !== '' && isset($options[$requested])) {
            $this->competition_code = $requested;
            session(['competition' => $requested]);
            return;
        }

        $this->competition_code = isset($options[$defaultCode]) ? $defaultCode : (string) array_key_first($options);
        session(['competition' => $this->competition_code]);
    }

    public function addSector(): void
    {
        $sector = preg_replace('/\s+/', ' ', trim($this->newSector)) ?? '';

        if ($sector === '') {
            $this->sectorFeedbackType = 'error';
            $this->sectorFeedback = 'Digite o nome do setor antes de adicionar.';
            $this->notify('error', 'Setor inválido', $this->sectorFeedback);
            return;
        }

        if (collect($this->sectors)->contains(fn (string $item) => mb_strtolower($item) === mb_strtolower($sector))) {
            $this->sectorFeedbackType = 'error';
            $this->sectorFeedback = 'Esse setor já foi adicionado.';
            $this->notify('error', 'Setor duplicado', $this->sectorFeedback);
            return;
        }

        $this->sectors[] = $sector;
        $this->newSector = '';
        $this->sectorFeedbackType = 'success';
        $this->sectorFeedback = 'Setor adicionado.';
        $this->notify('success', 'Setor adicionado', "Setor \"{$sector}\" adicionado.");
    }

    public function removeSector(int $index): void
    {
        $removed = $this->sectors[$index] ?? null;
        array_splice($this->sectors, $index, 1);
        $this->sectors = array_values($this->sectors);
        $this->sectorFeedbackType = 'success';
        $this->sectorFeedback = $removed ? "Setor \"{$removed}\" removido." : 'Setor removido.';
        $this->notify('success', 'Setor removido', $this->sectorFeedback);
    }

    public function addTieBreaker(): void
    {
        $criterion = trim($this->newTieBreaker);
        if (
            $criterion === '' ||
            in_array($criterion, $this->tieBreakers, true) ||
            ! in_array($criterion, self::AVAILABLE_TIE_BREAKERS, true) ||
            ! in_array($criterion, $this->enabledTieBreakerCriteria(), true)
        ) {
            $this->notify('error', 'Critério inválido', 'Selecione um critério válido que ainda não tenha sido adicionado.');
            return;
        }

        $this->tieBreakers[] = $criterion;
        $this->newTieBreaker = '';
        $this->notify('success', 'Critério adicionado', $this->tieBreakerLabels()[$criterion] ?? $criterion);
    }

    public function removeTieBreaker(int $index): void
    {
        $removed = $this->tieBreakers[$index] ?? null;
        array_splice($this->tieBreakers, $index, 1);
        $this->tieBreakers = array_values($this->tieBreakers);
        $this->notify('success', 'Critério removido', $removed ? ($this->tieBreakerLabels()[$removed] ?? $removed) : 'Critério removido.');
    }

    public function reorderTieBreakers(string $item, int $position): void
    {
        $from = array_search($item, $this->tieBreakers, true);
        if ($from === false) {
            return;
        }

        $to = max(0, min($position, count($this->tieBreakers) - 1));
        if ($from === $to) {
            return;
        }

        $value = $this->tieBreakers[$from];
        array_splice($this->tieBreakers, $from, 1);
        array_splice($this->tieBreakers, $to, 0, [$value]);
        $this->tieBreakers = array_values($this->tieBreakers);
        $this->notify('success', 'Ordem atualizada', 'Prioridade de desempate atualizada.');
    }

    public function save(): void
    {
        $this->appendPendingSector();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'competition_code' => ['required', 'string', 'in:'.implode(',', array_keys($this->availableCompetitionOptions()))],
            'description' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:3000'],
            'visibility' => ['required', 'in:private,invite_only,public'],
            'allow_prediction_changes' => ['boolean'],
            'prediction_lock_minutes' => ['required', 'integer', 'min:10'],
            'allow_pending_member_predictions' => ['boolean'],
            'points_exact_score' => ['required', 'integer', 'min:0', 'max:20'],
            'points_correct_result' => ['required', 'integer', 'min:0', 'max:20'],
            'points_correct_goals' => ['required', 'integer', 'min:0', 'max:20'],
            'correct_goals_mode' => ['required', 'in:both_teams,winner_only'],
            'sectors' => ['array', 'max:30'],
            'sectors.*' => ['string', 'max:80'],
            'tieBreakers' => ['array', 'max:5'],
            'tieBreakers.*' => ['string', 'in:'.implode(',', self::AVAILABLE_TIE_BREAKERS)],
        ]);

        $enabledTieBreakers = $this->enabledTieBreakerCriteria();
        $data['tieBreakers'] = array_values(array_filter(
            $data['tieBreakers'] ?? [],
            fn (string $item) => in_array($item, $enabledTieBreakers, true)
        ));

        $userId = (int) Auth::id();
        $context = $this->resolveCompetitionContext($data['competition_code']);

        $pool = DB::transaction(function () use ($data, $userId): Pool {
            $context = $this->resolveCompetitionContext($data['competition_code']);

            $pool = Pool::create([
                'owner_id' => $userId,
                'competition_id' => $context['competition']->id,
                'competition_season_id' => $context['season']->id,
                'name' => $data['name'],
                'slug' => $this->makeUniqueSlug($data['name']),
                'description' => $data['description'] ?: null,
                'instructions' => $data['instructions'] ?: null,
                'sectors' => !empty($data['sectors']) ? array_values($data['sectors']) : null,
                'tie_breakers' => !empty($data['tieBreakers']) ? $data['tieBreakers'] : null,
                'visibility' => $data['visibility'],
                'status' => 'active',
                'invite_code' => $this->makeUniqueInviteCode(),
                'allow_prediction_changes' => $data['allow_prediction_changes'],
                'prediction_lock_minutes' => $data['prediction_lock_minutes'],
                'allow_pending_member_predictions' => $data['allow_pending_member_predictions'],
                'points_exact_score' => $data['points_exact_score'],
                'points_correct_result' => $data['points_correct_result'],
                'points_correct_goals' => $data['points_correct_goals'],
                'correct_goals_mode' => $data['correct_goals_mode'],
                'stage' => $context['stage'],
            ]);

            $pool->members()->create([
                'user_id' => $userId,
                'role' => PoolMemberRole::Owner->value,
                'status' => PoolMemberStatus::Active->value,
                'activated_by' => $userId,
                'activated_at' => now(),
            ]);

            return $pool;
        });

        session()->flash('status', 'Bolão criado com sucesso!');
        $this->redirectRoute('pools.show', ['pool' => $pool->slug, 'competition' => $context['competition']->code], navigate: true);
    }

    private function appendPendingSector(): void
    {
        $sector = trim($this->newSector);

        if ($sector === '' || in_array($sector, $this->sectors, true)) {
            return;
        }

        $this->sectors[] = $sector;
        $this->newSector = '';
    }

    private function makeUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : 'bolao';
        $i = 2;

        while (Pool::query()->where('slug', $slug)->exists()) {
            $slug = ($base !== '' ? $base : 'bolao').'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function makeUniqueInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Pool::query()->where('invite_code', $code)->exists());

        return $code;
    }

    public function render()
    {
        $availableTieBreakers = collect($this->enabledTieBreakerCriteria())
            ->reject(fn (string $key) => in_array($key, $this->tieBreakers, true))
            ->values()
            ->all();

        $this->tieBreakers = array_values(array_filter(
            $this->tieBreakers,
            fn (string $item) => in_array($item, $this->enabledTieBreakerCriteria(), true)
        ));

        return view('livewire.pools.poolcreate', [
            'availableTieBreakers' => $availableTieBreakers,
            'tieBreakerLabels' => $this->tieBreakerLabels(),
            'competitionName' => (string) config('football-data.competitions.'.$this->competition_code.'.name', $this->competition_code),
        ]);
    }

    /**
     * @return string[]
     */
    private function enabledTieBreakerCriteria(): array
    {
        $enabled = [];

        if ($this->points_exact_score > 0) {
            $enabled[] = 'exact_scores';
        }

        if ($this->points_correct_result > 0) {
            $enabled[] = 'correct_results';
        }

        if ($this->points_correct_goals > 0) {
            $enabled[] = 'correct_home_goals';
            $enabled[] = 'correct_away_goals';
        }

        $enabled[] = 'predictions_counted';

        return $enabled;
    }

    /**
     * @return array<string, string>
     */
    private function tieBreakerLabels(): array
    {
        return [
            'exact_scores' => 'Mais placares exatos',
            'correct_results' => 'Mais resultados corretos',
            'correct_home_goals' => 'Mais gols do mandante corretos',
            'correct_away_goals' => 'Mais gols do visitante corretos',
            'predictions_counted' => 'Mais palpites válidos',
        ];
    }

    private function notify(string $icon, string $title, string $text): void
    {
        $this->dispatch('swal:toast', compact('icon', 'title', 'text'));
    }

    /**
     * @return array<string, string>
     */
    private function availableCompetitionOptions(): array
    {
        $user = Auth::user();
        $configured = (array) config('football-data.competitions', []);
        $options = [];

        foreach ($configured as $code => $settings) {
            $enabled = (bool) data_get($settings, 'enabled', false);
            $normalizedCode = strtoupper((string) $code);
            if (! $enabled && $normalizedCode !== 'WC' && !((bool) ($user?->is_admin ?? false))) {
                continue;
            }

            $label = (string) data_get($settings, 'name', $normalizedCode);
            if ($user && ! $user->canAccessCompetition($normalizedCode)) {
                continue;
            }
            $options[$normalizedCode] = $label !== '' ? $label : $normalizedCode;
        }

        if ($options === []) {
            $options['WC'] = 'Copa do Mundo';
        }

        return $options;
    }

    /**
     * @return array{competition: Competition, season: CompetitionSeason, stage: string}
     */
    private function resolveCompetitionContext(string $competitionCode): array
    {
        $code = strtoupper(trim($competitionCode));
        $competition = Competition::query()->where('code', $code)->first();
        if (! $competition) {
            abort(422, 'Competição inválida.');
        }

        $configuredSeason = (int) config('football-data.competitions.'.$code.'.season', 0);
        $season = CompetitionSeason::query()
            ->where('competition_id', $competition->id)
            ->where('year', $configuredSeason)
            ->latest('id')
            ->first();

        if (! $season) {
            abort(422, 'Temporada da competição ainda não sincronizada.');
        }

        $stage = (string) config('football-data.competitions.'.$code.'.default_stage', 'GROUP_STAGE');

        return [
            'competition' => $competition,
            'season' => $season,
            'stage' => $stage,
        ];
    }
}
