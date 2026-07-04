<?php

namespace App\Mail;

use App\Http\Controllers\UnsubscribeController;
use App\Models\User;

class DigestMail extends TemplatedMailable
{
    public function __construct(
        private readonly User $user,
        private readonly int $count,
        private readonly string $itemsHtml,
    ) {}

    protected function templateSlug(): string
    {
        return 'digest';
    }

    protected function templateVars(): array
    {
        return [
            'username' => $this->user->username ?? $this->user->name,
            'count' => $this->count,
            'items_html' => $this->itemsHtml,
            'view_url' => config('app.url'),
            'unsubscribe_url' => UnsubscribeController::signedUrl($this->user, 'digest'),
        ];
    }
}
