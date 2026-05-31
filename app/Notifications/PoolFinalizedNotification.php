<?php

namespace App\Notifications;

use App\Models\Pool;
use App\Models\PoolRanking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class PoolFinalizedNotification extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, PoolRanking>  $rankings
     * @param  array<string, int>  $stats
     */
    public function __construct(
        private readonly Pool $pool,
        private readonly Collection $rankings,
        private readonly array $stats,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $winner = $this->rankings->first();
        $winnerName = $winner?->user?->public_name ?? '—';
        $myRow = $this->rankings->firstWhere('user_id', $notifiable->id);

        $mail = (new MailMessage)
            ->subject("Bolão finalizado: {$this->pool->name}")
            ->greeting("Olá, {$notifiable->public_name}!")
            ->line("O bolão \"{$this->pool->name}\" foi finalizado.")
            ->line("Vencedor: {$winnerName}")
            ->line("Sua posição: ".($myRow?->position ? "{$myRow->position}º" : 'não classificado'))
            ->line("Sua pontuação: ".(int) ($myRow?->points_total ?? 0).' pts')
            ->line('Ranking final:');

        foreach ($this->rankings->take(20) as $row) {
            $name = $row->user?->public_name ?? 'Usuário';
            $highlight = (int) $row->user_id === (int) $notifiable->id ? ' (você)' : '';
            $mail->line("{$row->position}º {$name}{$highlight} - {$row->points_total} pts");
        }

        return $mail
            ->line('Resumo estatístico conforme regras do bolão:')
            ->line('Placares exatos: '.$this->stats['exact_scores'])
            ->line('Resultados corretos: '.$this->stats['correct_results'])
            ->line('Gols mandante corretos: '.$this->stats['correct_home_goals'])
            ->line('Gols visitante corretos: '.$this->stats['correct_away_goals'])
            ->line('Palpites computados: '.$this->stats['predictions_counted']);
    }
}
