<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class MentionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $mentioner,
        private readonly string $context,
        private readonly string $body,
        private readonly ?string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'mention',
            'mentioner_id' => $this->mentioner->id,
            'mentioner_username' => $this->mentioner->username,
            'message' => ($this->mentioner->username ?? $this->mentioner->name)." mentioned you in {$this->context}",
            'preview' => Str::limit($this->body, 120),
            'action_url' => $this->url,
        ];
    }
}
