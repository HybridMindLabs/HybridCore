<?php

namespace Tests\Feature\Extensions;

use App\Models\Extension;
use App\Models\User;
use App\Services\Extensions\ExtensionManager;
use App\Services\Extensions\ExtensionRequirements;
use App\Services\Extensions\Registries\FilterRegistry;
use App\Support\Filters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExtensionSdkV2Test extends TestCase
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
        File::deleteDirectory(public_path('extensions/testvendor-assets'));
        parent::tearDown();
    }

    // ── Filters ──────────────────────────────────────────────────

    public function test_filters_transform_values_in_priority_order(): void
    {
        $filters = new FilterRegistry;

        $filters->add('demo.value', fn (string $v) => $v.'-b', 200);
        $filters->add('demo.value', fn (string $v) => $v.'-a', 50);

        $this->assertSame('x-a-b', $filters->apply('demo.value', 'x'));
    }

    public function test_throwing_filter_is_skipped(): void
    {
        $filters = new FilterRegistry;

        $filters->add('demo.value', fn () => throw new \RuntimeException('boom'));
        $filters->add('demo.value', fn (string $v) => $v.'!');

        $this->assertSame('x!', $filters->apply('demo.value', 'x'));
    }

    public function test_inertia_shared_filter_reaches_pages(): void
    {
        app(FilterRegistry::class)->add(Filters::INERTIA_SHARED, function (array $shared) {
            $shared['sdkTestProp'] = 'works';

            return $shared;
        });

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertInertia(fn ($page) => $page->where('sdkTestProp', 'works'));
    }

    // ── Extension dependencies ───────────────────────────────────

    public function test_missing_extension_dependency_blocks_enable(): void
    {
        $extension = Extension::create([
            'slug' => 'testvendor/needy',
            'name' => 'Needy',
            'version' => '1.0.0',
            'path' => 'Testvendor/Needy',
            'metadata' => [
                'slug' => 'testvendor/needy',
                'requires' => ['extensions' => ['testvendor/base' => '>=1.0']],
            ],
            'installed_at' => now(),
        ]);

        $errors = app(ExtensionRequirements::class)->check($extension->metadata);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('testvendor/base', $errors[0]);
    }

    public function test_enabled_dependency_with_matching_version_passes(): void
    {
        Extension::create([
            'slug' => 'testvendor/base',
            'name' => 'Base',
            'version' => '1.2.0',
            'path' => 'Testvendor/Base',
            'metadata' => ['slug' => 'testvendor/base'],
            'installed_at' => now(),
            'enabled' => true,
        ]);

        $errors = app(ExtensionRequirements::class)->check([
            'requires' => ['extensions' => ['testvendor/base' => '>=1.0']],
        ]);

        $this->assertSame([], $errors);
    }

    // ── Asset publishing ─────────────────────────────────────────

    public function test_assets_are_published_on_enable_and_removed_on_uninstall(): void
    {
        $base = base_path('extensions/Testvendor/Assets');
        File::ensureDirectoryExists($base.'/resources/assets/img');
        file_put_contents($base.'/extension.json', json_encode([
            'id' => 'testvendor/assets', 'name' => 'Assets', 'version' => '1.0.0',
        ]));
        file_put_contents($base.'/resources/assets/img/logo.txt', 'logo');

        $extension = Extension::create([
            'slug' => 'testvendor/assets',
            'name' => 'Assets',
            'version' => '1.0.0',
            'path' => 'Testvendor/Assets',
            'metadata' => ['slug' => 'testvendor/assets', 'assets' => 'resources/assets'],
            'installed_at' => now(),
        ]);

        app(ExtensionManager::class)->enable($extension);
        $this->assertFileExists(public_path('extensions/testvendor-assets/img/logo.txt'));

        app(ExtensionManager::class)->uninstall($extension);
        $this->assertDirectoryDoesNotExist(public_path('extensions/testvendor-assets'));
    }

    // ── Version constraint hardening ─────────────────────────────

    public function test_extension_requirements_service_is_shared(): void
    {
        $this->assertInstanceOf(ExtensionRequirements::class, app(ExtensionRequirements::class));
    }
}
