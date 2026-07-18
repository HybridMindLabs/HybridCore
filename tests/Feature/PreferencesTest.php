<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    public function test_saving_a_timezone_and_locale_persists_them(): void
    {
        $user = User::factory()->create(['locale' => null, 'timezone' => null]);

        $this->actingAs($user)
            ->from(route('account.index'))
            ->put(route('account.preferences.update'), ['locale' => 'bg', 'timezone' => 'Europe/Sofia'])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('bg', $user->locale);
        $this->assertSame('Europe/Sofia', $user->timezone);
    }

    /** Empty means "no preference", which is null — not an empty string. */
    public function test_clearing_a_field_stores_null_rather_than_an_empty_string(): void
    {
        $user = User::factory()->create(['locale' => 'bg', 'timezone' => 'Europe/Sofia']);

        $this->actingAs($user)
            ->from(route('account.index'))
            ->put(route('account.preferences.update'), ['locale' => '', 'timezone' => '']);

        $user->refresh();

        $this->assertNull($user->locale);
        $this->assertNull($user->timezone);
    }

    public function test_an_invalid_timezone_is_rejected(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Sofia']);

        $this->actingAs($user)
            ->from(route('account.index'))
            ->put(route('account.preferences.update'), ['timezone' => 'Middle/Earth'])
            ->assertSessionHasErrors('timezone');

        $this->assertSame('Europe/Sofia', $user->fresh()->timezone);
    }

    /**
     * This endpoint used to also write a list-shaped notification_preferences
     * into the column the Email Preferences page keeps as a map. It must not
     * touch that column at all any more.
     */
    public function test_saving_preferences_leaves_email_preferences_alone(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => ['email_messages' => false, 'email_digest' => true],
        ]);

        $this->actingAs($user)
            ->from(route('account.index'))
            ->put(route('account.preferences.update'), ['locale' => 'en', 'timezone' => 'UTC'])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['email_messages' => false, 'email_digest' => true],
            $user->fresh()->notification_preferences,
        );
    }
}
