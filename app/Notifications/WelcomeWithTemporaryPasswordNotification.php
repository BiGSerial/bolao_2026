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
        $displayName   = $notifiable->display_name ?? 'Não informado';
        $appName       = config('app.name');

        return (new MailMessage)
            ->subject("Bem-vindo(a) ao {$appName} — seus dados de acesso")
            ->greeting("Olá, {$recipientName}!")
            ->line('Seu cadastro foi criado com sucesso. Confira abaixo seus dados de acesso:')
            ->line("**Apelido:** {$displayName}")
            ->line("**Senha temporária:** {$this->temporaryPassword}")
            ->line('Por segurança, você deverá criar uma nova senha no primeiro acesso.')
            ->action('Acessar agora', route('login'))
            ->line('Se você não solicitou este cadastro ou recebeu este e-mail por engano, desconsidere-o.')
            ->salutation('Atenciosamente,');
    }
}
