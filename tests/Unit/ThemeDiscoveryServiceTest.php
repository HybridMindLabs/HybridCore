<?php

namespace Tests\Unit;

use App\Services\Themes\ThemeDiscoveryService;
use ReflectionClass;
use Tests\TestCase;

class ThemeDiscoveryServiceTest extends TestCase
{
    private ThemeDiscoveryService $service;

    private string $themeDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->themeDir = sys_get_temp_dir().'/hc_theme_'.uniqid();
        mkdir($this->themeDir, 0755, true);
        $this->service = new ThemeDiscoveryService;
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->themeDir);
        parent::tearDown();
    }

    private function discoverIn(string $dir): array
    {
        $ref = new ReflectionClass(ThemeDiscoveryService::class);
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

            $manifest = $item->getPathname().'/theme.json';
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
                $manifest = $sub->getPathname().'/theme.json';
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
        $this->assertSame([], $this->discoverIn('/nonexistent_hc_theme_'.uniqid()));
    }

    public function test_discovers_flat_manifest(): void
    {
        mkdir("{$this->themeDir}/Default");
        file_put_contents("{$this->themeDir}/Default/theme.json", json_encode([
            'name' => 'Default',
            'slug' => 'default',
            'version' => '1.0.0',
        ]));

        $result = $this->discoverIn($this->themeDir);

        $this->assertCount(1, $result);
        $this->assertSame('default', $result[0]['slug']);
        $this->assertSame('Default', $result[0]['path']);
    }

    public function test_discovers_vendor_name_manifest(): void
    {
        mkdir("{$this->themeDir}/Vendor/DarkPro", 0755, true);
        file_put_contents("{$this->themeDir}/Vendor/DarkPro/theme.json", json_encode([
            'name' => 'Dark Pro',
            'slug' => 'dark-pro',
            'version' => '2.0.0',
        ]));

        $result = $this->discoverIn($this->themeDir);

        $this->assertCount(1, $result);
        $this->assertSame('Vendor/DarkPro', $result[0]['path']);
    }

    public function test_ignores_manifest_missing_required_fields(): void
    {
        mkdir("{$this->themeDir}/BadTheme");
        file_put_contents("{$this->themeDir}/BadTheme/theme.json", json_encode([
            'name' => 'No Slug Or Version',
        ]));

        $this->assertSame([], $this->discoverIn($this->themeDir));
    }

    public function test_ignores_invalid_json(): void
    {
        mkdir("{$this->themeDir}/BrokenTheme");
        file_put_contents("{$this->themeDir}/BrokenTheme/theme.json", '{ not valid json ');

        $this->assertSame([], $this->discoverIn($this->themeDir));
    }

    public function test_discovers_multiple_themes(): void
    {
        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            mkdir("{$this->themeDir}/{$name}");
            file_put_contents("{$this->themeDir}/{$name}/theme.json", json_encode([
                'name' => $name,
                'slug' => strtolower($name),
                'version' => '1.0.0',
            ]));
        }

        $this->assertCount(3, $this->discoverIn($this->themeDir));
    }

    public function test_manifest_data_merged_with_path(): void
    {
        mkdir("{$this->themeDir}/MyTheme");
        file_put_contents("{$this->themeDir}/MyTheme/theme.json", json_encode([
            'name' => 'My Theme',
            'slug' => 'my-theme',
            'version' => '1.0.0',
            'author' => 'Dev',
            'preview_image' => 'screenshot.png',
        ]));

        $result = $this->discoverIn($this->themeDir);

        $this->assertArrayHasKey('path', $result[0]);
        $this->assertSame('my-theme', $result[0]['slug']);
        $this->assertSame('Dev', $result[0]['author']);
        $this->assertSame('screenshot.png', $result[0]['preview_image']);
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
