<?php

namespace Tests\Feature\Admin;

use App\Models\Extension;
use App\Models\User;
use App\Providers\ExtensionServiceProvider;
use App\Services\Extensions\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class ExtensionImportSafetyTest extends TestCase
{
    use RefreshDatabase;

    private string $id;

    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->id = 'zztest/ext'.strtolower(substr(md5(uniqid('', true)), 0, 10));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('extensions/'.$this->id));
        foreach (glob(base_path('extensions/'.$this->id).'.*') ?: [] as $leftover) {
            File::deleteDirectory($leftover);
        }
        if (is_dir(base_path('extensions/zztest')) && count(scandir(base_path('extensions/zztest'))) === 2) {
            rmdir(base_path('extensions/zztest'));
        }
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /** @param  array<string, string>  $files  path inside the archive root => contents */
    private function archive(string $version, array $files = [], ?callable $tweak = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'hcext').'.zip';
        $this->tempFiles[] = $path;

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('ext/extension.json', json_encode(array_filter([
            'id' => $this->id,
            'name' => 'Safety Test',
            'version' => $version,
            'migrations' => isset($files['database/migrations/2099_01_01_000000_break.php']) ? 'database/migrations' : null,
        ])));
        $zip->addFromString('ext/VERSION.txt', $version);
        foreach ($files as $name => $contents) {
            $zip->addFromString('ext/'.$name, $contents);
        }
        if ($tweak) {
            $tweak($zip);
        }
        $zip->close();

        return new UploadedFile($path, 'ext.zip', 'application/zip', null, true);
    }

    private function brokenMigration(): string
    {
        return "<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nreturn new class extends Migration {\n    public function up(): void { throw new \\RuntimeException('boom'); }\n};\n";
    }

    private function manager(): ExtensionManager
    {
        return app(ExtensionManager::class);
    }

    public function test_backslash_paths_are_rejected(): void
    {
        $this->expectExceptionMessage('unsafe path');

        $this->manager()->importZip($this->archive('1.0.0', [], fn (ZipArchive $z) => $z->addFromString('ext/..\\..\\evil.php', 'x')));
    }

    public function test_symlinks_are_rejected(): void
    {
        $this->expectExceptionMessage('symbolic link');

        $this->manager()->importZip($this->archive('1.0.0', [], function (ZipArchive $z) {
            $z->addFromString('ext/link', '/etc/passwd');
            $z->setExternalAttributesName('ext/link', ZipArchive::OPSYS_UNIX, (0120777 << 16));
        }));
    }

    public function test_archives_with_too_many_files_are_rejected(): void
    {
        $this->expectExceptionMessage('too many files');

        $this->manager()->importZip($this->archive('1.0.0', [], function (ZipArchive $z) {
            for ($i = 0; $i <= ExtensionManager::MAX_ARCHIVE_ENTRIES; $i++) {
                $z->addFromString("ext/f/{$i}.txt", '');
            }
        }));

        $this->assertDirectoryDoesNotExist(base_path('extensions/'.$this->id));
    }

    public function test_a_failed_update_restores_the_previous_version(): void
    {
        $extension = $this->manager()->importZip($this->archive('1.0.0'));
        $this->manager()->enable($extension);

        try {
            $this->manager()->importZip($this->archive('2.0.0', [
                'database/migrations/2099_01_01_000000_break.php' => $this->brokenMigration(),
            ]));
            $this->fail('The broken update should have thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('rolled back', $e->getMessage());
        }

        $this->assertSame('1.0.0', trim((string) file_get_contents(base_path('extensions/'.$this->id.'/VERSION.txt'))));
        $this->assertSame('1.0.0', $extension->fresh()->version);
        $this->assertSame([], glob(storage_path('app/extension-swap/*')));
    }

    public function test_a_successful_update_replaces_the_files(): void
    {
        $extension = $this->manager()->importZip($this->archive('1.0.0'));

        $this->manager()->importZip($this->archive('1.1.0'));

        $this->assertSame('1.1.0', trim((string) file_get_contents(base_path('extensions/'.$this->id.'/VERSION.txt'))));
        $this->assertSame('1.1.0', $extension->fresh()->version);
        $this->assertSame([], glob(storage_path('app/extension-swap/*')));
    }

    public function test_enabling_an_extension_whose_migration_fails_leaves_it_disabled(): void
    {
        $extension = $this->manager()->importZip($this->archive('1.0.0', [
            'database/migrations/2099_01_01_000000_break.php' => $this->brokenMigration(),
        ]));

        try {
            $this->manager()->enable($extension);
            $this->fail('Enable should have thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Could not enable', $e->getMessage());
        }

        $this->assertFalse($extension->fresh()->enabled);
        $this->assertInstanceOf(Extension::class, $extension->fresh());
    }

    public function test_a_boot_failure_is_shown_on_the_extensions_page(): void
    {
        file_put_contents(storage_path('installed.lock'), 'installed');
        $this->tempFiles[] = storage_path('installed.lock');

        Extension::create([
            'name' => 'Demo', 'slug' => 'hybridcore/demo', 'version' => '1.0.0', 'author' => 'x',
            'type' => 'official', 'path' => 'hybridcore/demo', 'enabled' => true,
        ]);
        Cache::forever(ExtensionServiceProvider::BOOT_ERRORS_KEY, [
            'hybridcore/demo' => ['message' => 'Error: Class "Missing" not found', 'at' => now()->toIso8601String()],
        ]);

        $this->get(route('admin.extensions.index'))
            ->assertInertia(fn ($page) => $page->where('extensions.0.boot_error.message', 'Error: Class "Missing" not found'));
    }
}
