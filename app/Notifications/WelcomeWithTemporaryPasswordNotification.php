<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeWithTemporaryPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $temporaryPassword)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recipientName = $notifiable->public_name ?? $notifiable->name ?? 'Usuário';
        $displayName = $notifiable->display_name ?? 'Não informado';

        return (new MailMessage)
            ->subject('Acesso inicial da conta')
            ->greeting('Olá '.$recipientName.',')
            ->line('Seu pré-cadastro foi realizado com sucesso.')
            ->line('Apelido: '.$displayName)
            ->line('Senha temporária: '.$this->temporaryPassword)
            ->line('No primeiro login você será obrigado a trocar a senha.')
            ->action('Entrar no sistema', route('login'))
            ->line('Se você não solicitou este cadastro, ignore este e-mail.');
    }
}
