<?php

namespace Tests\Feature\Admin;

use App\Jobs\RebuildAssetsJob;
use App\Models\Extension;
use App\Models\ExtensionSetting;
use App\Models\User;
use App\Services\Extensions\ExtensionManager;
use App\Services\Extensions\ExtensionSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminExtensionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    public function test_settings_resolver_merges_defaults_overrides_and_drops_malformed_entries(): void
    {
        $ext = Extension::factory()->create([
            'path' => 'hybridcore/demo',
            'metadata' => [
                'settings_schema' => [
                    ['key' => 'greeting', 'type' => 'text', 'label' => 'Greeting', 'default' => 'Hi'],
                    ['label' => 'No key or type'],
                ],
            ],
        ]);

        $resolver = app(ExtensionSettingsResolver::class);
        $this->assertSame(['greeting' => 'Hi'], $resolver->effective($ext));

        ExtensionSetting::create(['extension_id' => $ext->id, 'key' => 'greeting', 'value' => 'Hey', 'type' => 'string']);
        $this->assertSame(['greeting' => 'Hey'], $resolver->effective($ext));
    }

    public function test_settings_persists_valid_value_rejects_invalid_and_ignores_unknown_key(): void
    {
        $ext = Extension::factory()->create([
            'path' => 'hybridcore/demo',
            'metadata' => [
                'settings_schema' => [
                    ['key' => 'accent', 'type' => 'color', 'label' => 'Accent', 'default' => '#22d3ee'],
                ],
            ],
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/extensions/{$ext->id}/settings", ['accent' => '#ff0000', 'not_in_schema' => 'x'])
            ->assertRedirect();

        $this->assertDatabaseHas('extension_settings', ['extension_id' => $ext->id, 'key' => 'accent', 'value' => '#ff0000']);
        $this->assertDatabaseMissing('extension_settings', ['extension_id' => $ext->id, 'key' => 'not_in_schema']);

        $this->actingAs($this->admin)
            ->post("/admin/extensions/{$ext->id}/settings", ['accent' => 'not-a-color'])
            ->assertSessionHasErrors('accent');
    }

    public function test_a_license_key_can_be_stored_and_removed(): void
    {
        $ext = Extension::factory()->create(['path' => 'hybridcore/demo']);

        $this->actingAs($this->admin)
            ->post("/admin/extensions/{$ext->id}/license", ['license_key' => ' lic_abc123 '])
            ->assertRedirect();

        // Surrounding whitespace from a copy-paste must not become part of the
        // bearer token.
        $this->assertSame('lic_abc123', $ext->fresh()->license_key);

        $this->actingAs($this->admin)
            ->post("/admin/extensions/{$ext->id}/license", ['license_key' => ''])
            ->assertRedirect();

        $this->assertNull($ext->fresh()->license_key);
    }

    public function test_the_stored_license_key_is_never_sent_to_the_browser(): void
    {
        $ext = Extension::factory()->create(['path' => 'hybridcore/demo']);
        $ext->license_key = 'lic_abc123';
        $ext->save();

        $response = $this->actingAs($this->admin)->get("/admin/extensions/{$ext->id}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('extension.has_license', true)
                ->missing('extension.license_key'));

        $response->assertDontSee('lic_abc123', false);
    }

    public function test_storing_a_license_requires_the_manage_permission(): void
    {
        $ext = Extension::factory()->create(['path' => 'hybridcore/demo']);
        $guest = User::factory()->create(['is_admin' => false]);

        $this->actingAs($guest)
            ->post("/admin/extensions/{$ext->id}/license", ['license_key' => 'lic_abc123'])
            ->assertRedirect('/admin/login');

        $this->assertNull($ext->fresh()->license_key);
    }

    public function test_extension_index_requires_admin(): void
    {
        $guest = User::factory()->create(['is_admin' => false]);

        $this->actingAs($guest)->get('/admin/extensions')
            ->assertRedirect('/admin/login');
    }

    public function test_extension_index_accessible_to_admin(): void
    {
        $this->actingAs($this->admin)->get('/admin/extensions')
            ->assertStatus(200);
    }

    public function test_extension_index_returns_extensions_and_rebuild_status(): void
    {
        Extension::factory()->create(['path' => 'hybridcore/demo']);
        Extension::factory()->create(['path' => 'hybridcore/announcements']);
        Extension::factory()->create(['path' => 'hybridcore/giveaways']);

        $this->actingAs($this->admin)->get('/admin/extensions')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Extensions/Index')
                ->has('extensions', 3)
                ->has('rebuild')
                ->where('rebuild.status', 'done')
            );
    }

    public function test_sync_requires_admin(): void
    {
        $guest = User::factory()->create(['is_admin' => false]);

        $this->actingAs($guest)->post('/admin/extensions/sync')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_can_sync_extensions(): void
    {
        $this->mock(ExtensionManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('sync')->once()->andReturn(2);
            $mock->shouldReceive('rebuildStatus')->andReturn('done');
            $mock->shouldReceive('lastRebuildAt')->andReturn(null);
        });

        $this->actingAs($this->admin)->post('/admin/extensions/sync')
            ->assertRedirect('/admin/extensions')
            ->assertSessionHas('success');
    }

    public function test_admin_can_enable_extension(): void
    {
        Queue::fake();

        $extension = Extension::factory()->create(['enabled' => false]);

        $this->actingAs($this->admin)
            ->post("/admin/extensions/{$extension->id}/enable")
            ->assertRedirect('/admin/extensions');

        $this->assertDatabaseHas('extensions', ['id' => $extension->id, 'enabled' => true]);
        Queue::assertPushed(RebuildAssetsJob::class);
    }

    public function test_admin_can_disable_extension(): void
    {
        Queue::fake();

        $extension = Extension::factory()->create(['enabled' => true]);

        $this->actingAs($this->admin)
            ->post("/admin/extensions/{$extension->id}/disable")
            ->assertRedirect('/admin/extensions');

        $this->assertDatabaseHas('extensions', ['id' => $extension->id, 'enabled' => false]);
        Queue::assertPushed(RebuildAssetsJob::class);
    }

    public function test_rebuild_route_queues_job(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post('/admin/extensions/rebuild')
            ->assertRedirect('/admin/extensions')
            ->assertSessionHas('success');

        Queue::assertPushed(RebuildAssetsJob::class);
    }

    public function test_show_page_returns_extension_data_and_rebuild_status(): void
    {
        $extension = Extension::factory()->create();

        $this->actingAs($this->admin)
            ->get("/admin/extensions/{$extension->id}")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Extensions/Show')
                ->has('extension')
                ->where('extension.id', $extension->id)
                ->has('rebuild')
            );
    }
}
