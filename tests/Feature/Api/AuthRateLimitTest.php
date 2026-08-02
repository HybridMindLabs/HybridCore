<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'wrong'])
                ->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_register_is_throttled_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/register', ['email' => 'not-an-email'])
                ->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/register', ['email' => 'not-an-email'])
            ->assertStatus(429);
    }
}
