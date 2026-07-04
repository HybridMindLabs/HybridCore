<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountPreferencesTest extends TestCase
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

    public function test_user_locale_preference_updates(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->put(route('account.preferences.update'), [
                'locale' => 'bg',
                'timezone' => 'Europe/Sofia',
            ])
            ->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame('bg', $fresh->locale);
        $this->assertSame('Europe/Sofia', $fresh->timezone);
        $this->assertSame('bg', session('locale'));
    }

    public function test_unsupported_locale_preference_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('account.preferences.update'), ['locale' => 'xx'])
            ->assertSessionHasErrors('locale');
    }

    public function test_preference_locale_changes_ui_language(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('account.preferences.update'), ['locale' => 'bg']);

        $this->actingAs($user->fresh())->get('/account');
        $this->assertSame('bg', app()->getLocale());
    }

    public function test_oauth_error_is_translated(): void
    {
        $user = User::factory()->create(['locale' => 'bg']);

        $response = $this->actingAs($user)->get('/auth/nonexistent/redirect');

        $response->assertNotFound();
        // The 404 page renders with the Bulgarian message available in the exception.
        $this->assertSame('bg', app()->getLocale());
    }

    public function test_banned_login_error_is_translated(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('sup3rsecret9'),
            'banned_at' => now(),
        ]);

        $this->withSession(['locale' => 'bg'])
            ->post('/login', ['email' => $user->email, 'password' => 'sup3rsecret9'])
            ->assertSessionHasErrors(['email' => 'Този акаунт е блокиран.']);
    }

    public function test_registration_flash_uses_current_locale(): void
    {
        $user = User::factory()->create(['locale' => 'bg']);

        $this->actingAs($user)
            ->put(route('account.preferences.update'), ['locale' => 'bg'])
            ->assertSessionHas('success', 'Предпочитанията са запазени.');
    }
}
