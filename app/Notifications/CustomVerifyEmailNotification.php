<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class CustomVerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue(config('queue-priority.mail.default', 'mail'));
    }

    protected function verificationUrl($notifiable): string
    {
        $path = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
            absolute: false
        );

        return URL::to($path);
    }

    public function toMail($notifiable): MailMessage
    {
        $recipientName    = $notifiable->public_name ?? $notifiable->name ?? 'Usuário';
        $verificationUrl  = $this->verificationUrl($notifiable);
        $appName          = config('app.name');

        return (new MailMessage)
            ->subject("Confirme seu e-mail para ativar sua conta — {$appName}")
            ->greeting("Olá, {$recipientName}!")
            ->line('Obrigado pelo cadastro. Para ativar sua conta, confirme seu endereço de e-mail no botão abaixo.')
            ->action('Confirmar e-mail', $verificationUrl)
            ->line('Este link de confirmação expira em **60 minutos**.')
            ->line('Se você não criou uma conta em nossa plataforma, desconsidere este e-mail.')
            ->salutation('Atenciosamente,');
    }
}
