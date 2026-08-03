<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The per-IP login limiter doesn't slow down a distributed attempt against
 * one account spread across many addresses — this is the secondary limiter
 * that closes that gap.
 */
class LoginAccountLockoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        RateLimiter::clear('login-account:victim@example.com');
        parent::tearDown();
    }

    public function test_a_distributed_attempt_against_one_account_is_throttled(): void
    {
        $user = User::factory()->create(['email' => 'victim@example.com', 'password' => Hash::make('correct-password')]);

        // Each attempt from a different IP, so the per-IP limiter never trips —
        // only the per-account one can catch this.
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.{$i}.1"])
                ->post('/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.99.1'])
            ->post('/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertStatus(429);

        $this->assertGuest();
    }

    public function test_attempts_against_a_different_account_are_unaffected(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.com', 'password' => Hash::make('wrong-target')]);
        $other = User::factory()->create(['email' => 'other@example.com', 'password' => Hash::make('correct-password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.{$i}.1"])
                ->post('/login', ['email' => $victim->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        // A different account, still under its own limit, logs in fine.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.50.1'])
            ->post('/login', ['email' => $other->email, 'password' => 'correct-password'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($other);
    }
}
