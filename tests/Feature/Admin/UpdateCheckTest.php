<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The scheduled update check is the only thing that tells an operator a release
 * exists, so its two promises matter: administrators hear about a new version,
 * and they hear about it exactly once.
 */
class UpdateCheckTest extends TestCase
{
    use RefreshDatabase;

    private function fakeRelease(string $tag): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                'tag_name' => $tag,
                'name' => "HybridCore {$tag}",
                'html_url' => "https://github.com/example/core/releases/tag/{$tag}",
                'body' => 'Release notes.',
                'published_at' => '2026-01-01T00:00:00Z',
            ]),
        ]);
    }

    public function test_administrators_are_notified_when_a_newer_release_appears(): void
    {
        Notification::fake();
        $this->fakeRelease('99.0.0');

        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create(['is_admin' => false]);

        $this->artisan('hybridcore:update:check', ['--notify' => true])->assertSuccessful();

        Notification::assertSentTo($admin, SystemNotification::class);
        Notification::assertNotSentTo($member, SystemNotification::class);
    }

    public function test_the_same_version_is_only_announced_once(): void
    {
        Notification::fake();
        $this->fakeRelease('99.0.0');

        $admin = User::factory()->create(['is_admin' => true]);

        $this->artisan('hybridcore:update:check', ['--notify' => true])->assertSuccessful();
        $this->artisan('hybridcore:update:check', ['--notify' => true])->assertSuccessful();

        // A daily schedule would otherwise nag every morning until they update.
        Notification::assertSentToTimes($admin, SystemNotification::class, 1);
    }

    public function test_nobody_is_notified_when_the_release_is_not_newer(): void
    {
        Notification::fake();
        $this->fakeRelease('0.0.1');

        User::factory()->create(['is_admin' => true]);

        $this->artisan('hybridcore:update:check', ['--notify' => true])->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_an_unreachable_release_feed_does_not_fail_the_scheduled_run(): void
    {
        Notification::fake();
        Http::fake(['api.github.com/*' => Http::response(null, 500)]);

        User::factory()->create(['is_admin' => true]);

        // A red scheduled task every night trains people to ignore the scheduler.
        $this->artisan('hybridcore:update:check', ['--notify' => true])->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_checking_without_notify_never_messages_anyone(): void
    {
        Notification::fake();
        $this->fakeRelease('99.0.0');

        User::factory()->create(['is_admin' => true]);

        $this->artisan('hybridcore:update:check')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull(Cache::get('hybridcore.update_announced_version'));
    }
}
