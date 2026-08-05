<?php

namespace Tests\Feature\Auth;

use App\Mail\AccountLockedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountLockoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');

        // Isolate lockout behavior from the separate per-minute/per-IP throttle.
        config(['hybridcore.login_rate_limit' => 1000]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    private function attemptWrongPassword(User $user): void
    {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'not-the-password']);
    }

    public function test_wrong_passwords_below_the_threshold_do_not_lock_the_account(): void
    {
        config(['hybridcore.max_failed_login_attempts' => 5]);
        $user = User::factory()->create(['password' => bcrypt('password')]);

        for ($i = 0; $i < 4; $i++) {
            $this->attemptWrongPassword($user);
        }

        $this->assertFalse($user->fresh()->isLockedOut());
        $this->assertSame(4, $user->fresh()->failed_login_attempts);
    }

    public function test_crossing_the_threshold_locks_the_account_and_emails_the_owner(): void
    {
        config(['hybridcore.max_failed_login_attempts' => 3, 'hybridcore.lockout_minutes' => 15]);
        Mail::fake();
        $user = User::factory()->create(['password' => bcrypt('password')]);

        for ($i = 0; $i < 3; $i++) {
            $this->attemptWrongPassword($user);
        }

        $fresh = $user->fresh();
        $this->assertTrue($fresh->isLockedOut());
        $this->assertSame(0, $fresh->failed_login_attempts);
        Mail::assertQueued(AccountLockedMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_a_locked_account_refuses_even_the_correct_password(): void
    {
        config(['hybridcore.max_failed_login_attempts' => 3]);
        $user = User::factory()->create(['password' => bcrypt('password'), 'locked_until' => now()->addMinutes(10)]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_successful_login_resets_the_failed_attempt_counter(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password'), 'failed_login_attempts' => 4]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('account.index'));

        $this->assertSame(0, $user->fresh()->failed_login_attempts);
    }

    public function test_admin_login_also_locks_out_after_the_threshold(): void
    {
        config(['hybridcore.max_failed_login_attempts' => 3]);
        $admin = User::factory()->create(['is_admin' => true, 'password' => bcrypt('password')]);

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'wrong']);
        }

        $this->assertTrue($admin->fresh()->isLockedOut());
    }

    public function test_admin_can_unlock_an_account_early(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $locked = User::factory()->create(['locked_until' => now()->addMinutes(10), 'failed_login_attempts' => 5]);

        $this->actingAs($admin)
            ->post(route('admin.users.unlock', $locked))
            ->assertRedirect();

        $fresh = $locked->fresh();
        $this->assertFalse($fresh->isLockedOut());
        $this->assertSame(0, $fresh->failed_login_attempts);
    }
}
