<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $sender,
        private readonly int $conversationId,
        private readonly string $preview,
    ) {}

    public function via(object $notifiable): array
    {
        $prefs = $notifiable->notification_preferences ?? [];
        $channels = ['database'];

        if (! in_array('dm_email', $prefs, true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_message',
            'sender_id' => $this->sender->id,
            'sender_username' => $this->sender->username,
            'sender_avatar' => $this->sender->avatar,
            'conversation_id' => $this->conversationId,
            'preview' => $this->preview,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.new_message_subject', ['name' => $this->sender->username]))
            ->line(__('notifications.new_message_body', ['name' => $this->sender->username]))
            ->line('"'.$this->preview.'"')
            ->action(__('notifications.view_message'), route('account.messages.show', $this->conversationId));
    }
}
