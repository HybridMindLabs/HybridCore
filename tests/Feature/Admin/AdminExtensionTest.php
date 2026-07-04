<?php

namespace Tests\Feature\Admin;

use App\Jobs\RebuildAssetsJob;
use App\Models\Extension;
use App\Models\User;
use App\Services\Extensions\ExtensionManager;
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
        Extension::factory()->create(['path' => 'HybridCore/Example']);

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
