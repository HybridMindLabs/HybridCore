<?php

namespace Tests\Feature\Extensions;

use App\Models\Extension;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The nightly counterpart to the core's update check. Its job is to speak up
 * once per release and stay quiet otherwise — a daily message about the same
 * pending update is how a scheduler earns a mail filter.
 */
class ExtensionUpdateCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    private function extension(string $slug, string $version = '1.0.0'): Extension
    {
        return Extension::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'version' => $version,
            'author' => 'Someone',
            'description' => '',
            'type' => 'community',
            'path' => "hybridcore/{$slug}",
            'enabled' => true,
            'metadata' => ['update_url' => "https://feed.test/{$slug}.json"],
        ]);
    }

    private function fakeFeed(string $version = '2.0.0'): void
    {
        Http::fake(['feed.test/*' => Http::response([
            'version' => $version,
            'download_url' => 'https://example.test/pkg.zip',
        ])]);
    }

    public function test_administrators_hear_about_an_extension_release(): void
    {
        Notification::fake();
        $this->fakeFeed();
        $this->extension('demo');

        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create(['is_admin' => false]);

        $this->artisan('hybridcore:extensions:check-updates', ['--notify' => true])
            ->assertSuccessful();

        Notification::assertSentTo($admin, SystemNotification::class);
        Notification::assertNotSentTo($member, SystemNotification::class);
    }

    public function test_several_updates_arrive_as_one_message(): void
    {
        Notification::fake();
        $this->fakeFeed();
        $this->extension('demo');
        $this->extension('vote');

        $admin = User::factory()->create(['is_admin' => true]);

        $this->artisan('hybridcore:extensions:check-updates', ['--notify' => true])
            ->assertSuccessful();

        // Two pending updates must not mean two nightly notifications.
        Notification::assertSentToTimes($admin, SystemNotification::class, 1);
    }

    public function test_a_pending_update_is_not_announced_again_the_next_night(): void
    {
        Notification::fake();
        $this->fakeFeed();
        $this->extension('demo');

        $admin = User::factory()->create(['is_admin' => true]);

        $this->artisan('hybridcore:extensions:check-updates', ['--notify' => true])->assertSuccessful();
        $this->artisan('hybridcore:extensions:check-updates', ['--notify' => true])->assertSuccessful();

        Notification::assertSentToTimes($admin, SystemNotification::class, 1);
    }

    public function test_nothing_is_sent_when_everything_is_current(): void
    {
        Notification::fake();
        $this->fakeFeed('1.0.0');
        $this->extension('demo');

        User::factory()->create(['is_admin' => true]);

        $this->artisan('hybridcore:extensions:check-updates', ['--notify' => true])
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_an_unreachable_feed_does_not_fail_the_scheduled_run(): void
    {
        Notification::fake();
        Http::fake(['feed.test/*' => Http::response(null, 500)]);
        $this->extension('demo');

        User::factory()->create(['is_admin' => true]);

        $this->artisan('hybridcore:extensions:check-updates', ['--notify' => true])
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_checking_without_notify_stays_silent(): void
    {
        Notification::fake();
        $this->fakeFeed();
        $this->extension('demo');

        User::factory()->create(['is_admin' => true]);

        $this->artisan('hybridcore:extensions:check-updates')->assertSuccessful();

        Notification::assertNothingSent();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
