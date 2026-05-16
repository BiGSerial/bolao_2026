<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeWithTemporaryPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $temporaryPassword)
    {
        $this->onQueue(config('queue-priority.mail.default', 'mail'));
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
            ->subject("Acesso criado no {$appName} — senha temporária")
            ->greeting("Olá, {$recipientName}!")
            ->line('Seu acesso foi criado com sucesso. Confira abaixo seus dados iniciais:')
            ->line("**Apelido:** {$displayName}")
            ->line("**Senha temporária:** {$this->temporaryPassword}")
            ->line('Por segurança, você deverá criar uma nova senha no primeiro acesso em até **13 horas**.')
            ->action('Acessar agora', route('login'))
            ->line('Se você não solicitou este cadastro ou recebeu este e-mail por engano, desconsidere-o.')
            ->salutation('Atenciosamente,');
    }
}
