<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeedbackReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $category,
        private readonly string $description,
        private readonly int $userId,
        private readonly string $userEmail,
        private readonly string $userName,
    ) {
        $this->onQueue(config('queue-priority.mail.default', 'mail'));
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $categoryLabel = match ($this->category) {
            'bug'        => '🐞 Bug',
            'suggestion' => '💡 Sugestão',
            default      => '💬 Outro',
        };

        $appName = config('app.name');

        return (new MailMessage)
            ->subject("[{$appName}] Novo feedback recebido: {$categoryLabel}")
            ->greeting('Novo feedback recebido!')
            ->line("**Categoria:** {$categoryLabel}")
            ->line("**Usuário:** {$this->userName} (#{$this->userId}) — {$this->userEmail}")
            ->line('**Mensagem:**')
            ->line($this->description)
            ->salutation('—');
    }
}
