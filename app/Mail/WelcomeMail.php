<?php

namespace App\Mail;

use App\Models\User;

class WelcomeMail extends TemplatedMailable
{
    public function __construct(private readonly User $user) {}

    protected function templateSlug(): string
    {
        return 'welcome';
    }

    protected function templateVars(): array
    {
        return [
            'username' => $this->user->username ?? $this->user->name,
            'login_url' => route('login'),
        ];
    }
}
