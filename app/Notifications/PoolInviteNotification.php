<?php

namespace App\Notifications;

use App\Models\PoolInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PoolInviteNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly PoolInvite $invite)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pool = $this->invite->pool;
        $url = route('pools.invites.accept', ['token' => $this->invite->token]);
        $recipientName = data_get($notifiable, 'public_name')
            ?? data_get($notifiable, 'name')
            ?? 'Usuário';

        return (new MailMessage)
            ->subject('Convite para o bolão '.$pool->name)
            ->greeting('Olá '.$recipientName.',')
            ->line('Você recebeu um convite para um bolão.')
            ->line('Bolão: '.$pool->name)
            ->line('Se você ainda não tem cadastro, crie sua conta e a entrada será feita automaticamente.')
            ->action('Aceitar convite', $url)
            ->line('Este convite expira em 7 dias.');
    }
}
