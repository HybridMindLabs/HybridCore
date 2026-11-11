<?php

namespace Tests\Feature\Admin;

use App\Games\Data\QueryResult;
use App\Models\Server;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\Extensions\Registries\HookRegistry;
use App\Services\ServerQueryService;
use App\Support\Hooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ServerOfflineNotifiesAdminsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_are_notified_only_on_the_online_to_offline_edge(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $server = Server::factory()->create();
        $service = app(ServerQueryService::class);

        // First-ever query, already down: not a transition, no notification.
        $service->record($server, QueryResult::offline('timeout'));
        Notification::assertNothingSent();

        // Comes online: no notification either (that's good news, not this feature).
        $service->record($server, new QueryResult(online: true));
        Notification::assertNothingSent();

        // Online -> offline: the edge this feature exists for.
        $service->record($server, QueryResult::offline('timeout'));
        Notification::assertSentTo($admin, SystemNotification::class);

        // Still offline next cycle: no repeat notification.
        $service->record($server, QueryResult::offline('timeout'));
        Notification::assertSentToTimes($admin, SystemNotification::class, 1);
    }

    public function test_offline_and_online_hooks_fire_only_on_the_edge(): void
    {
        Notification::fake();

        $fired = [];
        $hooks = app(HookRegistry::class);
        $hooks->listen(Hooks::SERVER_OFFLINE, function () use (&$fired) {
            $fired[] = 'offline';
        });
        $hooks->listen(Hooks::SERVER_ONLINE, function () use (&$fired) {
            $fired[] = 'online';
        });

        $server = Server::factory()->create();
        $service = app(ServerQueryService::class);

        // First-ever query is neither a drop nor a recovery.
        $service->record($server, new QueryResult(online: true));
        $service->record($server, new QueryResult(online: true));
        $service->record($server, QueryResult::offline('timeout'));
        $service->record($server, QueryResult::offline('timeout'));
        $service->record($server, new QueryResult(online: true));

        $this->assertSame(['offline', 'online'], $fired);
    }
}
