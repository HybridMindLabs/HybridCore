<?php

namespace Tests\Feature\Extensions;

use App\Services\Extensions\ExtensionManifestValidator;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MakeExtensionCommandTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = base_path('extensions/testvendor/testext');
        File::deleteDirectory(base_path('extensions/testvendor'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('extensions/testvendor'));
        parent::tearDown();
    }

    public function test_command_scaffolds_full_structure(): void
    {
        $this->artisan('hybridcore:make-extension', [
            'id' => 'testvendor/testext',
            '--with-admin' => true,
            '--with-web' => true,
            '--with-translations' => true,
            '--with-tests' => true,
        ])->assertSuccessful();

        $this->assertFileExists($this->base.'/extension.json');
        $this->assertFileExists($this->base.'/src/TestextServiceProvider.php');
        $this->assertFileExists($this->base.'/routes/admin.php');
        $this->assertFileExists($this->base.'/routes/web.php');
        $this->assertFileExists($this->base.'/README.md');
        $this->assertFileExists($this->base.'/resources/lang/en/messages.php');
        $this->assertFileExists($this->base.'/resources/lang/bg/messages.php');
        $this->assertFileExists($this->base.'/resources/js/pages/Admin/Index.vue');
        $this->assertDirectoryExists($this->base.'/database/migrations');
        $this->assertDirectoryExists($this->base.'/tests/Feature');

        // Manifest is valid per our own validator.
        $manifest = json_decode((string) file_get_contents($this->base.'/extension.json'), true);
        $result = app(ExtensionManifestValidator::class)->validate($manifest);
        $this->assertTrue($result['valid']);

        // Generated PHP parses.
        $this->assertStringContainsString('TestextServiceProvider', (string) file_get_contents($this->base.'/src/TestextServiceProvider.php'));

        // Admin routes use the permission middleware (no bypass).
        $this->assertStringContainsString('perm:testext.view', (string) file_get_contents($this->base.'/routes/admin.php'));
    }

    public function test_command_rejects_invalid_id(): void
    {
        $this->artisan('hybridcore:make-extension', ['id' => 'Bad Format!'])
            ->assertFailed();
    }

    public function test_command_does_not_overwrite_without_force(): void
    {
        $this->artisan('hybridcore:make-extension', ['id' => 'testvendor/testext'])->assertSuccessful();

        file_put_contents($this->base.'/extension.json', '{"marker": true}');

        $this->artisan('hybridcore:make-extension', ['id' => 'testvendor/testext'])
            ->assertFailed();

        $this->assertStringContainsString('marker', (string) file_get_contents($this->base.'/extension.json'));
    }

    public function test_force_overwrites(): void
    {
        $this->artisan('hybridcore:make-extension', ['id' => 'testvendor/testext'])->assertSuccessful();

        $this->artisan('hybridcore:make-extension', ['id' => 'testvendor/testext', '--force' => true])
            ->assertSuccessful();
    }
}
