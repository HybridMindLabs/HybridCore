<?php

namespace App\Mail;

use App\Models\User;

class AccountBannedMail extends TemplatedMailable
{
    public function __construct(
        private readonly User $user,
        private readonly string $reason = 'No reason provided.',
    ) {}

    protected function templateSlug(): string
    {
        return 'account_banned';
    }

    protected function templateVars(): array
    {
        return [
            'username' => $this->user->username ?? $this->user->name,
            'reason' => $this->reason,
            'appeal_url' => route('home'),
        ];
    }
}
