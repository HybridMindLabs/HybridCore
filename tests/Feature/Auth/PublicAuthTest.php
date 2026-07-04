<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicAuthTest extends TestCase
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

        parent::tearDown();
    }

    private function setSetting(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('hybridcore.settings');
    }

    public function test_user_can_register(): void
    {
        $this->post('/register', [
            'name' => 'New Player',
            'email' => 'player@example.com',
            'password' => 'sup3rsecret9',
            'password_confirmation' => 'sup3rsecret9',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'player@example.com', 'is_admin' => false]);
        $this->assertAuthenticated();
    }

    public function test_registration_can_be_disabled(): void
    {
        $this->setSetting('registration_enabled', '0');

        $this->post('/register', [
            'name' => 'Blocked',
            'email' => 'blocked@example.com',
            'password' => 'sup3rsecret9',
            'password_confirmation' => 'sup3rsecret9',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
    }

    public function test_user_can_login_and_login_is_recorded(): void
    {
        $user = User::factory()->create(['password' => Hash::make('sup3rsecret9')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'sup3rsecret9'])
            ->assertRedirect(route('account.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertNotNull($user->fresh()->last_login_ip);
    }

    public function test_banned_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('sup3rsecret9'),
            'banned_at' => now(),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'sup3rsecret9'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_password_reset_request_sends_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_updates_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'newpassw0rd99',
                'password_confirmation' => 'newpassw0rd99',
            ]);

            $response->assertRedirect(route('login'));

            return Hash::check('newpassw0rd99', $user->fresh()->password);
        });
    }

    public function test_email_verification_works(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->get($url)->assertRedirect(route('account.index'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_resend_verification_is_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => null]);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)->post('/email/verification-notification');
        }

        $this->actingAs($user)
            ->post('/email/verification-notification')
            ->assertStatus(429);

        Notification::assertSentToTimes($user, VerifyEmail::class, 3);
    }

    public function test_weak_password_is_rejected_on_register(): void
    {
        $this->post('/register', [
            'name' => 'Weak',
            'email' => 'weak@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }
}
