<?php

namespace Tests\Feature\Admin;

use App\Jobs\RebuildAssetsJob;
use App\Models\Extension;
use App\Services\Extensions\ExtensionDiscoveryService;
use App\Services\Extensions\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class ExtensionManagerTest extends TestCase
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

    // ── sync() ────────────────────────────────────────────────────────────────

    public function test_sync_upserts_discovered_extensions(): void
    {
        $this->mock(ExtensionDiscoveryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('discover')->once()->andReturn([
                [
                    'slug' => 'hybridcore/demo',
                    'name' => 'Demo',
                    'version' => '1.0.0',
                    'author' => 'HybridCore',
                    'description' => 'A demo extension.',
                    'type' => 'official',
                    'path' => 'hybridcore/demo',
                ],
            ]);
        });

        $manager = app(ExtensionManager::class);
        $count = $manager->sync();

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('extensions', ['slug' => 'hybridcore/demo', 'name' => 'Demo']);
    }

    public function test_sync_preserves_enabled_state_on_re_sync(): void
    {
        Extension::factory()->create([
            'slug' => 'hybridcore/demo',
            'enabled' => true,
            'path' => 'hybridcore/demo',
        ]);

        $this->mock(ExtensionDiscoveryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('discover')->once()->andReturn([
                [
                    'slug' => 'hybridcore/demo',
                    'name' => 'Demo Updated',
                    'version' => '1.1.0',
                    'author' => 'HybridCore',
                    'description' => '',
                    'type' => 'official',
                    'path' => 'hybridcore/demo',
                ],
            ]);
        });

        app(ExtensionManager::class)->sync();

        $ext = Extension::where('slug', 'hybridcore/demo')->first();
        $this->assertTrue($ext->enabled);
        $this->assertEquals('Demo Updated', $ext->name);
    }

    public function test_sync_returns_zero_when_no_extensions_found(): void
    {
        $this->mock(ExtensionDiscoveryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('discover')->once()->andReturn([]);
        });

        $count = app(ExtensionManager::class)->sync();

        $this->assertEquals(0, $count);
    }

    // ── enable() ──────────────────────────────────────────────────────────────

    public function test_enable_sets_enabled_true_and_timestamps(): void
    {
        Queue::fake();

        $extension = Extension::factory()->create(['enabled' => false, 'enabled_at' => null]);

        app(ExtensionManager::class)->enable($extension);

        $this->assertDatabaseHas('extensions', [
            'id' => $extension->id,
            'enabled' => true,
        ]);
        $this->assertNotNull($extension->fresh()->enabled_at);
        $this->assertNull($extension->fresh()->disabled_at);
    }

    public function test_enable_dispatches_rebuild_job(): void
    {
        Queue::fake();

        $extension = Extension::factory()->create(['enabled' => false]);

        app(ExtensionManager::class)->enable($extension);

        Queue::assertPushed(RebuildAssetsJob::class);
    }

    public function test_enable_sets_rebuild_status_to_pending(): void
    {
        Queue::fake();

        $extension = Extension::factory()->create(['enabled' => false]);

        app(ExtensionManager::class)->enable($extension);

        $this->assertEquals('pending', Cache::get('assets.rebuild_status'));
    }

    // ── disable() ─────────────────────────────────────────────────────────────

    public function test_disable_sets_enabled_false(): void
    {
        Queue::fake();

        $extension = Extension::factory()->create(['enabled' => true]);

        app(ExtensionManager::class)->disable($extension);

        $this->assertDatabaseHas('extensions', [
            'id' => $extension->id,
            'enabled' => false,
        ]);
        $this->assertNotNull($extension->fresh()->disabled_at);
    }

    public function test_disable_dispatches_rebuild_job(): void
    {
        Queue::fake();

        $extension = Extension::factory()->create(['enabled' => true]);

        app(ExtensionManager::class)->disable($extension);

        Queue::assertPushed(RebuildAssetsJob::class);
    }

    // ── rebuildStatus() ───────────────────────────────────────────────────────

    public function test_rebuild_status_returns_done_by_default(): void
    {
        Cache::forget('assets.rebuild_status');

        $status = app(ExtensionManager::class)->rebuildStatus();

        $this->assertEquals('done', $status);
    }

    public function test_dispatch_rebuild_marks_status_pending(): void
    {
        Queue::fake();

        app(ExtensionManager::class)->dispatchRebuild();

        $this->assertEquals('pending', app(ExtensionManager::class)->rebuildStatus());
    }
}
