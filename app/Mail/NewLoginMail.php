<?php

namespace App\Mail;

use App\Models\User;

class NewLoginMail extends TemplatedMailable
{
    public function __construct(
        private readonly User $user,
        private readonly string $ip,
        private readonly string $device,
    ) {}

    protected function templateSlug(): string
    {
        return 'new_login_detected';
    }

    protected function templateVars(): array
    {
        return [
            'username' => $this->user->username ?? $this->user->name,
            'ip_address' => $this->ip,
            'device' => $this->device,
            'at' => now()->toDayDateTimeString(),
            'sessions_url' => route('account.index', ['tab' => 'sessions']),
        ];
    }
}
