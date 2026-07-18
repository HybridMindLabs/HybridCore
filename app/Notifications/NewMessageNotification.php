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

        // Keyed lookup, matching how the Email Preferences page stores the
        // column. This used to be in_array('dm_email', $prefs) — a list lookup
        // against a map — which never matched, so the toggle did nothing and
        // the mail always went out. Absent means opted in.
        if (($prefs['email_messages'] ?? true) !== false) {
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
