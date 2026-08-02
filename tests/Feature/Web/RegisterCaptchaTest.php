<?php

namespace Tests\Feature\Web;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegisterCaptchaTest extends TestCase
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

    private function enableTurnstile(): void
    {
        Setting::updateOrCreate(['key' => 'captcha_provider'], ['value' => 'turnstile']);
        Setting::updateOrCreate(['key' => 'captcha_turnstile_site_key'], ['value' => 'site-key']);
        Setting::updateOrCreate(['key' => 'captcha_turnstile_secret_key'], ['value' => 'secret-key']);
        Cache::forget('hybridcore.settings');
    }

    public function test_registration_is_blocked_without_a_captcha_token(): void
    {
        $this->enableTurnstile();

        $this->post('/register', [
            'name' => 'New Player',
            'email' => 'player@example.com',
            'password' => 'sup3rsecret9',
            'password_confirmation' => 'sup3rsecret9',
        ])->assertSessionHasErrors('captcha_token');

        $this->assertDatabaseMissing('users', ['email' => 'player@example.com']);
        $this->assertGuest();
    }

    public function test_registration_is_blocked_when_captcha_verification_fails(): void
    {
        $this->enableTurnstile();

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $this->post('/register', [
            'name' => 'New Player',
            'email' => 'player@example.com',
            'password' => 'sup3rsecret9',
            'password_confirmation' => 'sup3rsecret9',
            'captcha_token' => 'bad-token',
        ])->assertSessionHasErrors('captcha_token');

        $this->assertDatabaseMissing('users', ['email' => 'player@example.com']);
    }

    public function test_registration_succeeds_with_a_valid_captcha_token(): void
    {
        $this->enableTurnstile();

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->post('/register', [
            'name' => 'New Player',
            'email' => 'player@example.com',
            'password' => 'sup3rsecret9',
            'password_confirmation' => 'sup3rsecret9',
            'captcha_token' => 'good-token',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'player@example.com']);
        $this->assertAuthenticated();
    }

    public function test_registration_works_without_a_token_when_captcha_is_disabled(): void
    {
        $this->post('/register', [
            'name' => 'New Player',
            'email' => 'player@example.com',
            'password' => 'sup3rsecret9',
            'password_confirmation' => 'sup3rsecret9',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'player@example.com']);
    }
}
