<?php

namespace App\Services\Predictions;

use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\Prediction;
use App\Models\User;
use Carbon\Carbon;
use DomainException;

class PredictionService
{
    public function save(Pool $pool, FootballMatch $match, User $user, int $homeScore, int $awayScore): Prediction
    {
        if (
            (int) $match->competition_id !== (int) $pool->competition_id ||
            (int) $match->competition_season_id !== (int) $pool->competition_season_id
        ) {
            throw new DomainException('Jogo fora da competição/temporada deste bolão.');
        }

        if ($match->stage !== $pool->stage) {
            throw new DomainException('Jogo fora da fase configurada para este bolao.');
        }

        $member = $pool->members()->where('user_id', $user->id)->first();

        if (! $member) {
            throw new DomainException('Usuario nao pertence a este grupo.');
        }

        if (in_array($member->status, ['inactive', 'removed', 'blocked'], true)) {
            throw new DomainException('Usuario nao esta habilitado para registrar palpites neste grupo.');
        }

        if ($member->status === 'pending' && ! $pool->allow_pending_member_predictions) {
            throw new DomainException('Este grupo nao permite palpites de participantes pendentes.');
        }

        if ($match->isFinished() || $match->isPredictionLockedFor($pool)) {
            throw new DomainException('Palpite bloqueado para este jogo.');
        }

        if ($pool->closed_predictions && $this->isClosedPredictionsWindowLocked($pool)) {
            throw new DomainException('Este bolão está com palpites fechados desde o início da primeira partida.');
        }

        $existing = Prediction::query()->where('pool_id', $pool->id)->where('football_match_id', $match->id)->where('user_id', $user->id)->first();
        if ($existing && ! $pool->allow_prediction_changes) {
            throw new DomainException('Este grupo nao permite alterar palpites ja registrados.');
        }

        $prediction = Prediction::updateOrCreate(
            ['pool_id' => $pool->id, 'football_match_id' => $match->id, 'user_id' => $user->id],
            ['home_score' => $homeScore, 'away_score' => $awayScore, 'last_changed_at' => now(), 'locked_at' => null, 'eligible' => true, 'ineligible_reason' => null]
        );

        $prediction->versions()->create([
            'changed_by' => $user->id,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'changed_at' => now(),
        ]);

        return $prediction;
    }

    private function isClosedPredictionsWindowLocked(Pool $pool): bool
    {
        $firstKickoffUtc = $this->firstKickoffUtcForPool($pool);
        if (! $firstKickoffUtc) {
            return false;
        }

        return now()->utc()->greaterThanOrEqualTo($firstKickoffUtc);
    }

    private function firstKickoffUtcForPool(Pool $pool): ?Carbon
    {
        $firstMatch = FootballMatch::query()
            ->where('competition_id', $pool->competition_id)
            ->where('competition_season_id', $pool->competition_season_id)
            ->where('stage', $pool->stage)
            ->orderBy('utc_date')
            ->first(['utc_date']);

        return $firstMatch?->utc_date;
    }
}
