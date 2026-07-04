<?php

namespace Tests\Feature\Extensions;

use App\Http\Controllers\Admin\UpdateController;
use App\Models\Extension;
use App\Models\User;
use App\Services\Extensions\ExtensionManager;
use App\Services\Extensions\ExtensionRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExtensionLifecycleTest extends TestCase
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
        File::deleteDirectory(base_path('extensions/Testvendor'));
        parent::tearDown();
    }

    // ── Requirements ─────────────────────────────────────────────

    public function test_version_constraints(): void
    {
        $req = new ExtensionRequirements;

        $this->assertTrue($req->satisfies('1.2.3', '>=1.0.0'));
        $this->assertFalse($req->satisfies('0.9.0', '>=1.0.0'));
        $this->assertTrue($req->satisfies('1.5.0', '^1.0'));
        $this->assertFalse($req->satisfies('2.0.0', '^1.0'));
        $this->assertTrue($req->satisfies('1.0.0', '1.0.0'));
        $this->assertTrue($req->satisfies('1.0.0', '=1.0.0'));
        $this->assertFalse($req->satisfies('1.0.1', '<1.0.0'));
        $this->assertTrue($req->satisfies('9.9.9', 'nonsense constraint'));
    }

    public function test_requires_core_blocks_enable(): void
    {
        $extension = Extension::create([
            'slug' => 'testvendor/future',
            'name' => 'Future',
            'version' => '1.0.0',
            'path' => 'Testvendor/Future',
            'metadata' => [
                'slug' => 'testvendor/future',
                'requires' => ['core' => '>=99.0.0'],
            ],
            'installed_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);

        app(ExtensionManager::class)->enable($extension);
    }

    public function test_satisfied_requires_allows_enable(): void
    {
        $extension = Extension::create([
            'slug' => 'testvendor/ok',
            'name' => 'Ok',
            'version' => '1.0.0',
            'path' => 'Testvendor/Ok',
            'metadata' => [
                'slug' => 'testvendor/ok',
                'requires' => ['core' => '>='.UpdateController::VERSION],
            ],
            'installed_at' => now(),
        ]);

        app(ExtensionManager::class)->enable($extension);

        $this->assertTrue($extension->fresh()->enabled);
    }

    // ── Uninstall ────────────────────────────────────────────────

    public function test_uninstall_removes_files_settings_and_record(): void
    {
        $base = base_path('extensions/Testvendor/Gone');
        File::ensureDirectoryExists($base);
        file_put_contents($base.'/extension.json', json_encode([
            'id' => 'testvendor/gone', 'name' => 'Gone', 'version' => '1.0.0',
        ]));

        $extension = Extension::create([
            'slug' => 'testvendor/gone',
            'name' => 'Gone',
            'version' => '1.0.0',
            'path' => 'Testvendor/Gone',
            'metadata' => ['slug' => 'testvendor/gone'],
            'installed_at' => now(),
            'enabled' => true,
        ]);
        $extension->settings()->create(['key' => 'foo', 'value' => 'bar']);

        app(ExtensionManager::class)->uninstall($extension);

        $this->assertDirectoryDoesNotExist($base);
        $this->assertDatabaseMissing('extensions', ['slug' => 'testvendor/gone']);
        $this->assertDatabaseMissing('extension_settings', ['key' => 'foo']);
    }

    public function test_admin_can_uninstall_via_http(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $extension = Extension::create([
            'slug' => 'testvendor/http',
            'name' => 'Http',
            'version' => '1.0.0',
            'path' => 'Testvendor/Http',
            'metadata' => ['slug' => 'testvendor/http'],
            'installed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.extensions.uninstall', $extension), ['drop_data' => true])
            ->assertRedirect(route('admin.extensions.index'));

        $this->assertDatabaseMissing('extensions', ['slug' => 'testvendor/http']);
    }
}
