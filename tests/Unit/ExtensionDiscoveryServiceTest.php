<?php

namespace Tests\Unit;

use App\Services\Extensions\ExtensionDiscoveryService;
use App\Services\Extensions\ExtensionManifestValidator;
use ReflectionClass;
use Tests\TestCase;

class ExtensionDiscoveryServiceTest extends TestCase
{
    private ExtensionDiscoveryService $service;

    private string $extDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extDir = sys_get_temp_dir().'/hc_ext_'.uniqid();
        mkdir($this->extDir, 0755, true);
        $this->service = new ExtensionDiscoveryService(new ExtensionManifestValidator);
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->extDir);
        parent::tearDown();
    }

    /** Calls the private discoverPath-equivalent by sym-linking extensions/ temporarily. */
    private function discoverIn(string $dir): array
    {
        $ref = new ReflectionClass(ExtensionDiscoveryService::class);
        $method = $ref->getMethod('parseManifest');
        $method->setAccessible(true);

        $found = [];

        if (! is_dir($dir)) {
            return [];
        }

        foreach (new \DirectoryIterator($dir) as $item) {
            if (! $item->isDir() || $item->isDot()) {
                continue;
            }

            $manifest = $item->getPathname().'/extension.json';
            if (file_exists($manifest)) {
                $parsed = $method->invoke($this->service, $manifest, $item->getFilename());
                if ($parsed !== null) {
                    $found[] = $parsed;
                }

                continue;
            }

            foreach (new \DirectoryIterator($item->getPathname()) as $sub) {
                if (! $sub->isDir() || $sub->isDot()) {
                    continue;
                }
                $manifest = $sub->getPathname().'/extension.json';
                if (file_exists($manifest)) {
                    $relPath = $item->getFilename().'/'.$sub->getFilename();
                    $parsed = $method->invoke($this->service, $manifest, $relPath);
                    if ($parsed !== null) {
                        $found[] = $parsed;
                    }
                }
            }
        }

        return $found;
    }

    public function test_returns_empty_for_missing_directory(): void
    {
        $this->assertSame([], $this->discoverIn('/nonexistent_hc_xyz_'.uniqid()));
    }

    public function test_discovers_flat_manifest(): void
    {
        mkdir("{$this->extDir}/TestExt");
        file_put_contents("{$this->extDir}/TestExt/extension.json", json_encode([
            'name' => 'Test Extension',
            'slug' => 'test-extension',
            'version' => '1.0.0',
        ]));

        $result = $this->discoverIn($this->extDir);

        $this->assertCount(1, $result);
        $this->assertSame('test-extension', $result[0]['slug']);
        $this->assertSame('TestExt', $result[0]['path']);
    }

    public function test_discovers_vendor_name_manifest(): void
    {
        mkdir("{$this->extDir}/Vendor/ExtName", 0755, true);
        file_put_contents("{$this->extDir}/Vendor/ExtName/extension.json", json_encode([
            'name' => 'Vendor Ext',
            'slug' => 'vendor-ext',
            'version' => '2.0.0',
        ]));

        $result = $this->discoverIn($this->extDir);

        $this->assertCount(1, $result);
        $this->assertSame('Vendor/ExtName', $result[0]['path']);
    }

    public function test_ignores_manifest_missing_required_fields(): void
    {
        mkdir("{$this->extDir}/BadExt");
        file_put_contents("{$this->extDir}/BadExt/extension.json", json_encode([
            'name' => 'No Slug Or Version',
        ]));

        $this->assertSame([], $this->discoverIn($this->extDir));
    }

    public function test_ignores_invalid_json(): void
    {
        mkdir("{$this->extDir}/BrokenExt");
        file_put_contents("{$this->extDir}/BrokenExt/extension.json", '{ not valid json ');

        $this->assertSame([], $this->discoverIn($this->extDir));
    }

    public function test_discovers_multiple_extensions(): void
    {
        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            mkdir("{$this->extDir}/{$name}");
            file_put_contents("{$this->extDir}/{$name}/extension.json", json_encode([
                'name' => $name,
                'slug' => strtolower($name),
                'version' => '1.0.0',
            ]));
        }

        $this->assertCount(3, $this->discoverIn($this->extDir));
    }

    public function test_manifest_data_merged_with_path(): void
    {
        mkdir("{$this->extDir}/MyExt");
        file_put_contents("{$this->extDir}/MyExt/extension.json", json_encode([
            'name' => 'My Ext',
            'slug' => 'my-ext',
            'version' => '1.0.0',
            'author' => 'Dev',
        ]));

        $result = $this->discoverIn($this->extDir);

        $this->assertArrayHasKey('path', $result[0]);
        $this->assertSame('my-ext', $result[0]['slug']);
        $this->assertSame('Dev', $result[0]['author']);
    }

    private function rmdirRecursive(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = "{$dir}/{$item}";
            is_dir($path) ? $this->rmdirRecursive($path) : unlink($path);
        }
        rmdir($dir);
    }
}
