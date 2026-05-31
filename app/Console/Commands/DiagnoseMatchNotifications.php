<?php

namespace App\Console\Commands;

use App\Models\MatchEvent;
use App\Models\MatchNotification;
use Illuminate\Console\Command;

class DiagnoseMatchNotifications extends Command
{
    protected $signature = 'notifications:diagnose-match {match_id : ID da partida} {--limit=20 : Limite de eventos/notificacoes}';

    protected $description = 'Diagnostica eventos e notificacoes de uma partida (realtime/webpush).';

    public function handle(): int
    {
        $matchId = (int) $this->argument('match_id');
        $limit = max(1, (int) $this->option('limit'));

        $events = MatchEvent::query()
            ->where('football_match_id', $matchId)
            ->latest('id')
            ->limit($limit)
            ->get();

        $notifications = MatchNotification::query()
            ->where('football_match_id', $matchId)
            ->latest('id')
            ->limit($limit)
            ->get();

        $this->info("Partida #{$matchId}");
        $this->line('');

        $this->info('Eventos');
        if ($events->isEmpty()) {
            $this->line('- nenhum evento encontrado');
        } else {
            $this->table(
                ['id', 'type', 'detail', 'minute', 'notified_at', 'fingerprint'],
                $events->map(fn (MatchEvent $e) => [
                    $e->id,
                    (string) $e->event_type,
                    (string) $e->event_detail,
                    (string) ($e->minute ?? '-'),
                    optional($e->notified_at)?->toDateTimeString() ?? '-',
                    substr((string) $e->fingerprint, 0, 12),
                ])->all()
            );
        }

        $this->line('');
        $this->info('Notificações');
        if ($notifications->isEmpty()) {
            $this->line('- nenhuma notificação encontrada');
        } else {
            $this->table(
                ['id', 'event_id', 'user_id', 'type', 'status', 'sent_at', 'channel'],
                $notifications->map(fn (MatchNotification $n) => [
                    $n->id,
                    (string) ($n->match_event_id ?? '-'),
                    (string) ($n->user_id ?? '-'),
                    (string) $n->type,
                    (string) $n->status,
                    optional($n->sent_at)?->toDateTimeString() ?? '-',
                    (string) ($n->channel ?? '-'),
                ])->all()
            );
        }

        return self::SUCCESS;
    }
}
