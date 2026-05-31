<?php

namespace App\Services\Matches;

class GoalNotificationTextBuilder
{
    public function build(array $data): array
    {
        $team = $this->value($data, 'team_name', 'Time');
        $player = $this->value($data, 'player_name', 'Jogador');
        $assist = $this->value($data, 'assist_name');
        $isDisallowed = (bool) ($data['is_disallowed'] ?? false);
        $detail = strtoupper((string) ($data['event_detail'] ?? ''));
        $minute = $this->minuteText($data['minute'] ?? null, $data['extra_minute'] ?? null);

        if ($isDisallowed) {
            $title = 'Anulou!';
            $homeTeam = $this->value($data, 'home_team_name', 'Casa');
            $awayTeam = $this->value($data, 'away_team_name', 'Visitante');
            $homeScore = $this->toIntOrNull($data['home_score'] ?? null);
            $awayScore = $this->toIntOrNull($data['away_score'] ?? null);
            $scoreText = ($homeScore !== null && $awayScore !== null)
                ? "{$homeScore}x{$awayScore}"
                : 'XxX';

            $body = "Gol de {$player} anulado pelo arbitro. Segue: {$homeTeam} {$scoreText} {$awayTeam}";

            return ['title' => $title, 'body' => $body];
        }

        $title = "Gol do {$team}";

        $goalNumber = $this->toIntOrNull($data['team_goal_number'] ?? null);
        $goalOrdinal = $goalNumber ? $this->ordinalGoal($goalNumber) : null;

        $body = "{$player} marca";

        if ($goalOrdinal !== null) {
            $body .= " o {$goalOrdinal} gol do {$team}";
        } else {
            $body .= " para {$team}";
        }

        if ($detail === 'PENALTY') {
            if ($goalOrdinal !== null) {
                $body = "De Penalty {$player} marca o {$goalOrdinal} para o {$team}";
            } else {
                $body = "De Penalty {$player} marca para o {$team}";
            }
        }

        if ($minute !== null) {
            $body .= " aos {$minute}";
        }

        if ($detail === 'OWN GOAL') {
            $body .= '. Gol contra';
        }

        $playerGoalNumber = $this->toIntOrNull($data['player_goal_number'] ?? null);
        if ($playerGoalNumber !== null && $playerGoalNumber >= 2) {
            $playerOrdinal = $this->ordinalGoal($playerGoalNumber);
            $body .= ". É o {$playerOrdinal} dele na partida";
        }

        if ($assist !== null && $assist !== '') {
            $body .= ". Assistência de {$assist}";
        }

        $body .= '.';

        return [
            'title' => $title,
            'body' => $body,
        ];
    }

    public function ordinalGoal(int $number): string
    {
        return match ($number) {
            1 => 'primeiro',
            2 => 'segundo',
            3 => 'terceiro',
            4 => 'quarto',
            5 => 'quinto',
            6 => 'sexto',
            7 => 'sétimo',
            8 => 'oitavo',
            9 => 'nono',
            10 => 'décimo',
            11 => 'décimo primeiro',
            12 => 'décimo segundo',
            13 => 'décimo terceiro',
            14 => 'décimo quarto',
            15 => 'décimo quinto',
            16 => 'décimo sexto',
            17 => 'décimo sétimo',
            18 => 'décimo oitavo',
            19 => 'décimo nono',
            20 => 'vigésimo',
            default => "{$number}º",
        };
    }

    private function minuteText(mixed $minute, mixed $extra): ?string
    {
        $m = $this->toIntOrNull($minute);
        if ($m === null) {
            return null;
        }

        $e = $this->toIntOrNull($extra);
        if ($e !== null && $e > 0) {
            return "{$m}'+{$e}";
        }

        return "{$m}'";
    }

    private function value(array $data, string $key, ?string $default = null): ?string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            return $default;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : $default;
    }

    private function toIntOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
