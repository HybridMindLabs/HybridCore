<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Changing a password is usually a reaction to it having leaked, so anything
 * still signed in on the old one has to go. It previously only rewrote the
 * hash and left every other session — and any "remember me" cookie — working.
 */
class PasswordChangeSignsOutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), 'installed');
        config(['session.driver' => 'database']);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    private function seedSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);
    }

    private function changePassword(User $user)
    {
        return $this->actingAs($user)
            ->from(route('account.index'))
            ->put(route('account.password.update'), [
                'current_password' => 'password',
                'password' => 'a-brand-new-password-1',
                'password_confirmation' => 'a-brand-new-password-1',
            ]);
    }

    public function test_changing_the_password_drops_other_sessions(): void
    {
        $user = User::factory()->create();
        $this->seedSession($user, 'other-device-session');

        $this->changePassword($user)->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-session']);
    }

    public function test_changing_the_password_invalidates_remember_me_cookies(): void
    {
        $user = User::factory()->create(['remember_token' => 'the-old-remember-token']);

        $this->changePassword($user)->assertSessionHasNoErrors();

        $this->assertNotSame('the-old-remember-token', $user->fresh()->remember_token);
    }

    public function test_a_rejected_password_change_leaves_sessions_alone(): void
    {
        $user = User::factory()->create(['remember_token' => 'the-old-remember-token']);
        $this->seedSession($user, 'other-device-session');

        $this->actingAs($user)
            ->from(route('account.index'))
            ->put(route('account.password.update'), [
                'current_password' => 'not-the-current-password',
                'password' => 'a-brand-new-password-1',
                'password_confirmation' => 'a-brand-new-password-1',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertDatabaseHas('sessions', ['id' => 'other-device-session']);
        $this->assertSame('the-old-remember-token', $user->fresh()->remember_token);
    }

    public function test_the_device_making_the_change_stays_signed_in(): void
    {
        $user = User::factory()->create();

        $this->changePassword($user);

        $this->assertTrue(auth()->check());
    }
}
