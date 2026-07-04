<?php

namespace App\Notifications;

use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewCommentNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $commenter,
        private readonly NewsArticle $article,
        private readonly string $body,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_comment',
            'commenter_id' => $this->commenter->id,
            'commenter_username' => $this->commenter->username,
            'article_slug' => $this->article->slug,
            'message' => ($this->commenter->username ?? $this->commenter->name)
                .' commented on "'.Str::limit($this->article->title, 60).'"',
            'preview' => Str::limit($this->body, 120),
            'action_url' => route('news.show', $this->article->slug),
        ];
    }
}
