<?php

namespace Tests\Feature\Installer;

use App\Models\Setting;
use App\Services\Installer\InstallationStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallationStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        @unlink(storage_path('installed.lock'));
        config(['app.installed' => false]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));

        parent::tearDown();
    }

    private function state(): InstallationStateService
    {
        return app(InstallationStateService::class);
    }

    public function test_app_redirects_to_installer_when_no_markers_exist(): void
    {
        $this->get('/')->assertRedirect(route('installer.welcome'));
    }

    public function test_lock_file_marks_app_installed(): void
    {
        file_put_contents(storage_path('installed.lock'), '{"installed":true}');

        $this->assertTrue($this->state()->isInstalled());
        $this->get('/')->assertOk();
    }

    public function test_env_flag_marks_app_installed(): void
    {
        config(['app.installed' => true]);

        $this->assertTrue($this->state()->isInstalled());
        $this->get('/')->assertOk();
    }

    public function test_database_flag_marks_app_installed(): void
    {
        Setting::create(['key' => 'installed_at', 'value' => now()->toIso8601String()]);

        $this->assertTrue($this->state()->isInstalled());
        $this->get('/')->assertOk();
    }

    public function test_installer_is_blocked_after_installation(): void
    {
        file_put_contents(storage_path('installed.lock'), '{"installed":true}');

        $this->get('/install')->assertRedirect(route('admin.login'));
    }

    public function test_no_redirect_loop_when_installed(): void
    {
        file_put_contents(storage_path('installed.lock'), '{"installed":true}');

        // /install redirects to admin.login, which must respond 200 (not redirect again).
        $response = $this->get('/install');
        $response->assertRedirect(route('admin.login'));

        $this->get(route('admin.login'))->assertOk();
    }

    public function test_mark_installed_writes_lock_file_with_json_metadata(): void
    {
        $this->state()->markInstalled(['source' => 'test']);

        $this->assertFileExists(storage_path('installed.lock'));

        $metadata = json_decode((string) file_get_contents(storage_path('installed.lock')), true);

        $this->assertTrue($metadata['installed']);
        $this->assertArrayHasKey('installed_at', $metadata);
        $this->assertArrayHasKey('version', $metadata);
        $this->assertArrayHasKey('environment', $metadata);
        $this->assertSame('test', $metadata['source']);

        // Database marker also written.
        $this->assertDatabaseHas('settings', ['key' => 'installed_at']);
    }

    public function test_unlock_requires_force(): void
    {
        file_put_contents(storage_path('installed.lock'), '{"installed":true}');

        $this->expectException(\RuntimeException::class);

        $this->state()->unlock();
    }

    public function test_unlock_with_force_removes_markers(): void
    {
        $this->state()->markInstalled();
        $this->state()->unlock(force: true);

        $this->assertFileDoesNotExist(storage_path('installed.lock'));
        $this->assertDatabaseMissing('settings', ['key' => 'installed_at']);
    }

    public function test_status_reports_all_markers(): void
    {
        $this->state()->markInstalled();

        $status = $this->state()->status();

        $this->assertTrue($status['lock_file']);
        $this->assertTrue($status['installed']);
        $this->assertIsArray($status['lock_metadata']);
        $this->assertTrue($status['database_flag']);
    }

    public function test_install_status_command_reports_state(): void
    {
        $this->artisan('hybridcore:install-status')
            ->expectsOutputToContain('NOT INSTALLED')
            ->assertSuccessful();

        $this->state()->markInstalled();

        $this->artisan('hybridcore:install-status')
            ->expectsOutputToContain('INSTALLED')
            ->assertSuccessful();
    }

    public function test_install_lock_command_creates_markers(): void
    {
        $this->artisan('hybridcore:install-lock')->assertSuccessful();

        $this->assertFileExists(storage_path('installed.lock'));
        $this->assertDatabaseHas('settings', ['key' => 'installed_at']);
    }

    public function test_install_unlock_command_requires_force(): void
    {
        file_put_contents(storage_path('installed.lock'), '{"installed":true}');

        $this->artisan('hybridcore:install-unlock')->assertFailed();
        $this->assertFileExists(storage_path('installed.lock'));

        $this->artisan('hybridcore:install-unlock', ['--force' => true])->assertSuccessful();
        $this->assertFileDoesNotExist(storage_path('installed.lock'));
    }
}
