<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $message,
        private readonly string $level = 'info', // info | success | warning | danger
        private readonly ?string $actionUrl = null,
        private readonly ?string $actionLabel = null,
    ) {}

    /** @var array<int, string> */
    private array $channels = ['database'];

    /** @param  array<int, string>  $channels */
    public function onChannels(array $channels): static
    {
        $copy = clone $this;
        $copy->channels = $channels;

        return $copy;
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'system',
            'level' => $this->level,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->line($this->message);

        if ($this->actionUrl && $this->actionLabel) {
            $mail->action($this->actionLabel, $this->actionUrl);
        }

        return $mail;
    }
}
