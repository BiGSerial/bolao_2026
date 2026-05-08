<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class CustomVerifyEmailNotification extends VerifyEmail
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $recipientName = $notifiable->public_name ?? $notifiable->name ?? 'Usuário';
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Confirmação de e-mail da conta')
            ->greeting('Olá '.$recipientName.',')
            ->line('Confirme seu endereço de e-mail para concluir a ativação da conta.')
            ->action('Confirmar e-mail', $verificationUrl)
            ->line('Se você não criou uma conta em nossa plataforma, ignore este e-mail.');
    }
}

