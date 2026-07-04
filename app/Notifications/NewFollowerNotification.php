<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewFollowerNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly User $follower) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_follower',
            'follower_id' => $this->follower->id,
            'follower_username' => $this->follower->username,
            'follower_avatar' => $this->follower->avatar,
            'message' => ($this->follower->username ?? $this->follower->name).' started following you',
            'action_url' => $this->follower->username ? route('profile.show', $this->follower->username) : null,
        ];
    }
}
