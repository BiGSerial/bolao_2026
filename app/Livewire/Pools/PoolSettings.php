<?php

namespace App\Livewire\Pools;

use App\Enums\PoolMemberRole;
use App\Models\Pool;
use App\Services\Predictions\PredictionScoringService;
use App\Services\Pools\PoolFinalizationService;
use App\Services\Pools\PoolRankingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class PoolSettings extends Component
{
    private const AVAILABLE_TIE_BREAKERS = [
        'exact_scores',
        'correct_results',
        'correct_home_goals',
        'correct_away_goals',
        'predictions_counted',
    ];

    public Pool $pool;
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
    public string $status = 'active';

    /** @var string[] */
    public array $sectors = [];
    public string $newSector = '';
    public string $sectorFeedback = '';
    public string $sectorFeedbackType = '';
    /** @var string[] */
    public array $tieBreakers = [];
    public string $newTieBreaker = '';
    public string $deleteChallenge = '';
    public string $deletePhrase = '';
    public bool $showDeleteConfirm = false;

    public function mount(Pool $pool): void
    {
        $this->pool = $pool;
        $this->assertManager();

        $this->name = $pool->name;
        $this->description = (string) ($pool->description ?? '');
        $this->instructions = (string) ($pool->instructions ?? '');
        $this->visibility = $pool->visibility;
        $this->allow_prediction_changes = (bool) $pool->allow_prediction_changes;
        $this->prediction_lock_minutes = (int) $pool->prediction_lock_minutes;
        $this->allow_pending_member_predictions = (bool) $pool->allow_pending_member_predictions;
        $this->points_exact_score = (int) ($pool->points_exact_score ?? 5);
        $this->points_correct_result = (int) ($pool->points_correct_result ?? 3);
        $this->points_correct_goals = (int) ($pool->points_correct_goals ?? 1);
        $this->correct_goals_mode = (string) ($pool->correct_goals_mode ?? 'both_teams');
        $this->status = $pool->status;
        $this->sectors = $pool->sectors ?? [];
        $this->tieBreakers = $this->sanitizeTieBreakers($pool->tie_breakers ?? []);
        $this->tieBreakers = array_values(array_filter(
            $this->tieBreakers,
            fn (string $item) => in_array($item, $this->enabledTieBreakerCriteria(), true)
        ));

        $this->regenerateDeleteChallenge();
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

    public function save(PoolRankingService $rankingService, PredictionScoringService $scoringService): void
    {
        $this->assertManager();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
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
            'status' => ['required', 'in:active,blocked,archived'],
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

        $this->pool->update([
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'instructions' => $data['instructions'] ?: null,
            'visibility' => $data['visibility'],
            'allow_prediction_changes' => $data['allow_prediction_changes'],
            'prediction_lock_minutes' => $data['prediction_lock_minutes'],
            'allow_pending_member_predictions' => $data['allow_pending_member_predictions'],
            'points_exact_score' => $data['points_exact_score'],
            'points_correct_result' => $data['points_correct_result'],
            'points_correct_goals' => $data['points_correct_goals'],
            'correct_goals_mode' => $data['correct_goals_mode'],
            'status' => $data['status'],
            'sectors' => !empty($data['sectors']) ? array_values($data['sectors']) : null,
            'tie_breakers' => !empty($data['tieBreakers']) ? $data['tieBreakers'] : null,
        ]);

        $freshPool = $this->pool->fresh();
        $scoringService->recalculatePool($freshPool);
        $rankingService->recalculate($freshPool);

        session()->flash('status', 'Configurações atualizadas com sucesso.');
        $this->notify('success', 'Configurações salvas', 'As alterações do bolão foram aplicadas.');
    }

    public function regenerateDeleteChallenge(): void
    {
        $this->deleteChallenge = Str::upper(Str::random(6));
        $this->deletePhrase = '';
    }

    public function openDeleteConfirm(): void
    {
        $this->showDeleteConfirm = true;
        $this->regenerateDeleteChallenge();
    }

    public function cancelDeleteConfirm(): void
    {
        $this->showDeleteConfirm = false;
        $this->deletePhrase = '';
    }

    public function deletePool(): void
    {
        $member = $this->pool->members()->where('user_id', Auth::id())->first();
        abort_unless($member && $member->role === PoolMemberRole::Owner->value, 403);

        $this->validate([
            'deletePhrase' => ['required', 'string', 'in:'.$this->deleteChallenge],
        ], [
            'deletePhrase.in' => 'A palavra de confirmação não confere.',
            'deletePhrase.required' => 'Digite a palavra de confirmação.',
        ]);

        $name = $this->pool->name;
        $this->pool->delete();

        session()->flash('status', "Grupo {$name} removido com sucesso.");
        $this->redirect(route('pools.index'), navigate: true);
    }

    public function finalizePool(PoolFinalizationService $finalizationService): void
    {
        $member = $this->pool->members()->where('user_id', Auth::id())->first();
        abort_unless($member && $member->role === PoolMemberRole::Owner->value, 403);

        if ((string) $this->pool->status === 'archived') {
            $this->notify('info', 'Bolão já finalizado', 'Este bolão já está encerrado.');
            return;
        }

        $finalizationService->finalize($this->pool);
        $this->pool->refresh();
        $this->status = (string) $this->pool->status;

        session()->flash('status', 'Bolão finalizado com sucesso. Ranking consolidado e e-mails enviados.');
        $this->notify('success', 'Bolão finalizado', 'Pontuações consolidadas e participantes notificados por e-mail.');
    }

    private function assertManager(): void
    {
        $isAdmin = (bool) Auth::user()?->is_admin;
        abort_if(! $isAdmin && $this->pool->status !== 'active', 403);

        $member = $this->pool->members()->where('user_id', Auth::id())->first();
        abort_if(! $member, 403);
        abort_unless(in_array($member->role, [PoolMemberRole::Owner->value, PoolMemberRole::Manager->value], true), 403);
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

        return view('livewire.pools.poolsettings', [
            'availableTieBreakers' => $availableTieBreakers,
            'tieBreakerLabels' => $this->tieBreakerLabels(),
        ]);
    }

    /**
     * @param  array<int, mixed>  $raw
     * @return string[]
     */
    private function sanitizeTieBreakers(array $raw): array
    {
        $clean = [];

        foreach ($raw as $item) {
            $value = (string) $item;
            if (in_array($value, self::AVAILABLE_TIE_BREAKERS, true) && ! in_array($value, $clean, true)) {
                $clean[] = $value;
            }
        }

        return $clean;
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

    private function notify(string $icon, string $title, string $text): void
    {
        $this->dispatch('swal:toast', compact('icon', 'title', 'text'));
    }
}
