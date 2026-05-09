<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class CustomVerifyEmailNotification extends VerifyEmail
{
    use Queueable;

    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
            absolute: false
        );
    }

    public function toMail($notifiable): MailMessage
    {
        $recipientName    = $notifiable->public_name ?? $notifiable->name ?? 'Usuário';
        $verificationUrl  = $this->verificationUrl($notifiable);
        $appName          = config('app.name');

        return (new MailMessage)
            ->subject("Confirme seu e-mail — {$appName}")
            ->greeting("Olá, {$recipientName}!")
            ->line('Obrigado por se cadastrar! Para ativar sua conta, confirme seu endereço de e-mail clicando no botão abaixo.')
            ->action('Confirmar e-mail', $verificationUrl)
            ->line('Este link de confirmação expira em **60 minutos**.')
            ->line('Se você não criou uma conta em nossa plataforma, desconsidere este e-mail.')
            ->salutation('Atenciosamente,');
    }
}
