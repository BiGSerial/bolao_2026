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
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Recuperação de senha solicitada')
            ->greeting('Olá '.$recipientName.',')
            ->line('Recebemos uma solicitação para redefinir a senha da sua conta.')
            ->action('Redefinir senha', $resetUrl)
            ->line('Se você não solicitou essa ação, ignore este e-mail.')
            ->line('Dados da solicitação:')
            ->line('IP: '.$this->context['ip'])
            ->line('Agente/Navegador: '.$this->context['user_agent'])
            ->line('Horário: '.$this->context['requested_at']);
    }
}

