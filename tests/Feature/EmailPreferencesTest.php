<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Direct Messages switch used to be decorative: via() did a list lookup
 * (in_array('dm_email', $prefs)) against a map the page writes by key, so it
 * never matched and the mail went out regardless. These tests hold the switch
 * to what the UI promises.
 */
class EmailPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function notification(): NewMessageNotification
    {
        return new NewMessageNotification(User::factory()->create(), 1, 'hello');
    }

    public function test_dm_email_is_sent_when_no_preference_has_been_saved(): void
    {
        $user = User::factory()->create(['notification_preferences' => null]);

        $this->assertContains('mail', $this->notification()->via($user));
    }

    public function test_turning_off_direct_messages_stops_the_email(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => ['email_messages' => false],
        ]);

        $this->assertNotContains('mail', $this->notification()->via($user));
    }

    public function test_the_in_app_notification_survives_the_email_being_off(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => ['email_messages' => false],
        ]);

        $this->assertContains('database', $this->notification()->via($user));
    }

    public function test_turning_off_the_digest_does_not_affect_dm_email(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => ['email_digest' => false],
        ]);

        $this->assertContains('mail', $this->notification()->via($user));
    }

    public function test_saving_preferences_leaves_unrelated_keys_untouched(): void
    {
        file_put_contents(storage_path('installed.lock'), 'installed');

        try {
            $user = User::factory()->create([
                'notification_preferences' => ['some_other_setting' => 'keep me'],
            ]);

            $this->actingAs($user)
                ->from(route('account.index'))
                ->put(route('account.email-preferences.update'), [
                    'email_messages' => false,
                    'email_digest' => true,
                ])
                ->assertSessionHasNoErrors();

            $prefs = $user->fresh()->notification_preferences;

            $this->assertSame('keep me', $prefs['some_other_setting']);
            $this->assertFalse($prefs['email_messages']);
            $this->assertTrue($prefs['email_digest']);
        } finally {
            @unlink(storage_path('installed.lock'));
        }
    }
}
