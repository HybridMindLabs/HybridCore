<?php

namespace Tests\Feature\Admin;

use App\Console\Commands\ReleaseBuildCommand;
use App\Http\Controllers\Admin\UpdateController;
use App\Models\User;
use App\Services\UpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed.lock'), 'installed');
        Cache::forget('hybridcore.latest_release');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    // ── UpdateService ────────────────────────────────────────────

    public function test_latest_release_detects_newer_version(): void
    {
        Http::fake(['api.github.com/*' => Http::response([
            'tag_name' => 'v99.0.0',
            'name' => 'HybridCore 99',
            'html_url' => 'https://github.com/x/releases/v99.0.0',
            'body' => 'Big release',
            'published_at' => '2026-07-01T00:00:00Z',
        ])]);

        $release = app(UpdateService::class)->latestRelease(fresh: true);

        $this->assertSame('99.0.0', $release['version']);
        $this->assertTrue($release['is_newer']);
    }

    public function test_current_version_is_not_flagged_as_newer(): void
    {
        Http::fake(['api.github.com/*' => Http::response([
            'tag_name' => 'v'.UpdateController::VERSION,
        ])]);

        $release = app(UpdateService::class)->latestRelease(fresh: true);

        $this->assertFalse($release['is_newer']);
    }

    public function test_github_failure_returns_null_gracefully(): void
    {
        Http::fake(['api.github.com/*' => Http::response([], 500)]);

        $this->assertNull(app(UpdateService::class)->latestRelease(fresh: true));
    }

    // ── Panel guardrails ─────────────────────────────────────────

    public function test_apply_is_blocked_when_panel_updates_disabled(): void
    {
        config(['hybridcore.panel_updates' => false]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->postJson(route('admin.updates.apply'))
            ->assertForbidden();
    }

    // ── Release packaging exclusions ─────────────────────────────

    public function test_release_excludes_secrets_dev_tooling_and_runtime_state(): void
    {
        $command = new ReleaseBuildCommand;

        foreach ([
            '.env', '.git/config', 'node_modules/vite/index.js', 'tests/Feature/SomeTest.php',
            'storage/logs/laravel.log', 'bootstrap/cache/config.php', 'vendor/autoload.php',
            '.ddev/config.yaml', 'extensions/hybridcore/demo/extension.json', 'phpunit.xml',
        ] as $path) {
            $this->assertTrue($command->isExcluded($path), "{$path} should be excluded");
        }

        foreach ([
            'app/Models/User.php', 'public/build/manifest.json', 'composer.json',
            'resources/js/app.ts', 'routes/web.php', 'artisan', '.env.example',
            'extensions/BUILDING_EXTENSIONS.md',
        ] as $path) {
            $this->assertFalse($command->isExcluded($path), "{$path} should ship");
        }

        $this->assertFalse($command->isExcluded('vendor/autoload.php', withVendor: true));
    }
}
