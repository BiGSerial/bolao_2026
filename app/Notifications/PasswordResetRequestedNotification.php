<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetRequestedNotification extends Notification
{
    use Queueable;

    /**
     * @param array{ip:string,user_agent:string,requested_at:string} $context
     */
    public function __construct(
        private readonly string $token,
        private readonly array $context
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recipientName = $notifiable->public_name ?? $notifiable->name ?? 'Usuário';
        $resetUrl      = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
        $appName = config('app.name');

        return (new MailMessage)
            ->subject("Redefinição de senha — {$appName}")
            ->greeting("Olá, {$recipientName}!")
            ->line('Recebemos uma solicitação para redefinir a senha da sua conta.')
            ->line('Clique no botão abaixo para criar uma nova senha. **O link expira em 60 minutos.**')
            ->action('Redefinir minha senha', $resetUrl)
            ->line('Se você não fez essa solicitação, recomendamos que troque sua senha imediatamente.')
            ->line('**Detalhes da solicitação:**')
            ->line("Endereço IP: {$this->context['ip']}")
            ->line("Navegador: {$this->context['user_agent']}")
            ->line("Data e hora: {$this->context['requested_at']}")
            ->salutation('Atenciosamente,');
    }
}
