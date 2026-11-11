<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\SettingsService;
use Database\Seeders\CorePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
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

    public function test_an_admin_change_records_old_and_new_values(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $server = Server::factory()->create(['name' => 'Old name']);

        $server->update(['name' => 'New name', 'last_queried_at' => now()]);

        $log = ActivityLog::where('event', 'server.updated')->where('subject_id', $server->id)->firstOrFail();
        $this->assertSame(['name' => 'Old name'], $log->properties['old']);
        $this->assertSame(['name' => 'New name'], $log->properties['new']);
    }

    public function test_changes_without_a_signed_in_person_are_not_logged(): void
    {
        $server = Server::factory()->create();
        $server->update(['name' => 'Renamed by a job']);

        $this->assertSame(0, ActivityLog::where('event', 'like', 'server.%')->count());
    }

    public function test_presence_churn_alone_is_not_logged(): void
    {
        $this->actingAs($admin = User::factory()->create(['is_admin' => true]));

        $admin->update(['last_seen_at' => now(), 'last_login_ip' => '1.2.3.4']);

        $this->assertSame(0, ActivityLog::where('event', 'user.updated')->count());
    }

    public function test_secret_settings_are_redacted(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $settings = app(SettingsService::class);

        $settings->set('stripe_secret_key', 'sk_live_first');
        $settings->set('stripe_secret_key', 'sk_live_second');
        $settings->set('app_name', 'Before');
        $settings->set('app_name', 'After');

        $secret = ActivityLog::where('event', 'setting.updated')->where('description', 'like', '%stripe_secret_key')->firstOrFail();
        $this->assertSame(['value' => '[redacted]'], $secret->properties['new']);
        $this->assertStringNotContainsString('sk_live', json_encode($secret->properties));

        $plain = ActivityLog::where('event', 'setting.updated')->where('description', 'like', '%app_name')->firstOrFail();
        $this->assertSame(['value' => 'Before'], $plain->properties['old']);
        $this->assertSame(['value' => 'After'], $plain->properties['new']);
    }

    public function test_a_rotated_webhook_secret_is_logged_without_its_value(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $endpoint = WebhookEndpoint::factory()->create(['secret' => 'whsec_old_value']);

        $endpoint->update(['secret' => 'whsec_new_value']);

        $log = ActivityLog::where('event', 'webhook.updated')->where('subject_id', $endpoint->id)->firstOrFail();
        $this->assertSame(['secret' => '[redacted]'], $log->properties['new']);
        $this->assertStringNotContainsString('whsec_', json_encode($log->properties));
        $this->assertStringNotContainsString('whsec_', (string) ActivityLog::pluck('properties')->toJson());
    }

    public function test_role_permission_changes_are_diffed(): void
    {
        (new CorePermissionsSeeder)->run();
        $admin = User::factory()->create(['is_admin' => true]);
        $role = Role::create(['name' => 'Mods', 'slug' => 'mods', 'color' => '#ffffff', 'icon' => 'ShieldCheck']);
        $role->permissions()->sync(Permission::whereIn('slug', ['users.view'])->pluck('id'));

        $this->actingAs($admin)->put(route('admin.roles.update', $role), [
            'name' => 'Mods',
            'slug' => 'mods',
            'color' => '#ffffff',
            'icon' => 'ShieldCheck',
            'permissions' => ['servers.view'],
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.roles.index'));

        $log = ActivityLog::where('event', 'role.permissions_changed')->firstOrFail();
        $this->assertSame(['servers.view'], $log->properties['added']);
        $this->assertSame(['users.view'], $log->properties['removed']);
    }
}
