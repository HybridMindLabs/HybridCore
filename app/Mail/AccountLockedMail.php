<?php

namespace App\Mail;

use App\Models\User;

class AccountLockedMail extends TemplatedMailable
{
    public function __construct(
        private readonly User $user,
        private readonly int $lockoutMinutes,
    ) {}

    protected function templateSlug(): string
    {
        return 'account_locked';
    }

    protected function templateVars(): array
    {
        return [
            'username' => $this->user->username ?? $this->user->name,
            'lockout_minutes' => $this->lockoutMinutes,
        ];
    }
}
