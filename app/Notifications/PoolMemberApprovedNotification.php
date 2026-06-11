<?php

namespace App\Notifications;

use App\Models\Pool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PoolMemberApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Pool $pool)
    {
        $this->onQueue(config('queue-priority.mail.default', 'mail'));
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');
        $name = data_get($notifiable, 'public_name') ?? data_get($notifiable, 'name') ?? 'Usuário';
        $url = config('app.url').'/pwa/pools/'.$this->pool->id;

        return (new MailMessage)
            ->subject("Você foi aprovado no bolão \"{$this->pool->name}\" — {$appName}")
            ->greeting("Olá, {$name}!")
            ->line("Sua participação no bolão **{$this->pool->name}** foi aprovada.")
            ->line('Agora você pode registrar seus palpites e acompanhar o ranking.')
            ->action('Acessar o bolão', $url)
            ->salutation('Atenciosamente,');
    }
}
