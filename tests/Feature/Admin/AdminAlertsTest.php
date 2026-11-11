<?php

namespace Tests\Feature\Admin;

use App\Games\Data\QueryResult;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\ServerQueryService;
use Database\Seeders\CorePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), 'installed');
        (new CorePermissionsSeeder)->run();
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    /** @param  array<int, string>  $slugs */
    private function staff(array $slugs): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Staff', 'slug' => 'staff-'.uniqid()]);
        $role->permissions()->sync(Permission::whereIn('slug', array_merge(['admin.access'], $slugs))->pluck('id'));
        $user->roles()->attach($role);

        return $user;
    }

    private function takeServerDown(): void
    {
        $server = Server::factory()->create();
        $service = app(ServerQueryService::class);
        $service->record($server, new QueryResult(online: true));
        $service->record($server, QueryResult::offline('timeout'));
    }

    public function test_server_alerts_reach_staff_who_can_view_servers(): void
    {
        Notification::fake();
        $serverStaff = $this->staff(['servers.view']);
        $newsStaff = $this->staff(['news.view']);
        $member = User::factory()->create();

        $this->takeServerDown();

        Notification::assertSentTo($serverStaff, SystemNotification::class, fn ($n, $channels) => $channels === ['database']);
        Notification::assertNotSentTo($newsStaff, SystemNotification::class);
        Notification::assertNotSentTo($member, SystemNotification::class);
    }

    public function test_an_admin_can_switch_an_alert_to_email_only(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put(route('admin.profile.alerts'), [
            'alerts' => [['key' => 'server_offline', 'database' => false, 'mail' => true]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->takeServerDown();

        Notification::assertSentTo($admin->fresh(), SystemNotification::class, fn ($n, $channels) => $channels === ['mail']);
    }

    public function test_turning_every_channel_off_silences_the_alert(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put(route('admin.profile.alerts'), [
            'alerts' => [['key' => 'server_offline', 'database' => false, 'mail' => false]],
        ]);

        $this->takeServerDown();

        Notification::assertNotSentTo($admin->fresh(), SystemNotification::class);
    }

    public function test_the_profile_lists_only_alerts_the_admin_can_act_on(): void
    {
        $this->actingAs($this->staff(['payments.view']))
            ->get(route('admin.profile.edit'))
            ->assertInertia(fn ($page) => $page
                ->has('alerts', 1)
                ->where('alerts.0.key', 'payment_failed'));
    }

    public function test_unknown_alert_keys_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->put(route('admin.profile.alerts'), [
                'alerts' => [['key' => 'everything', 'database' => true, 'mail' => true]],
            ])
            ->assertSessionHasErrors('alerts.0.key');
    }
}
