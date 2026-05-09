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
        $pool          = $this->invite->pool;
        $url           = route('pools.invites.accept', ['token' => $this->invite->token]);
        $recipientName = data_get($notifiable, 'public_name')
            ?? data_get($notifiable, 'name')
            ?? 'Usuário';
        $appName = config('app.name');

        return (new MailMessage)
            ->subject("Convite para o bolão \"{$pool->name}\" — {$appName}")
            ->greeting("Olá, {$recipientName}!")
            ->line("Você recebeu um convite para participar do bolão **{$pool->name}**.")
            ->line('Aceite o convite e comece a fazer seus palpites para concorrer com seus amigos.')
            ->action('Aceitar convite', $url)
            ->line('Ainda não tem cadastro? Não se preocupe — crie sua conta e a entrada no bolão será feita automaticamente.')
            ->line('Este convite expira em **7 dias**.')
            ->salutation('Atenciosamente,');
    }
}
