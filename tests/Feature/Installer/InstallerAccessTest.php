<?php

namespace Tests\Feature\Installer;

use App\Services\InstallerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class InstallerAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(storage_path('installed.lock'));
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    // ── Guard: not installed ──────────────────────────────────────────────────

    public function test_installer_welcome_accessible_when_not_installed(): void
    {
        $this->get('/install')->assertStatus(200);
    }

    public function test_installer_requirements_accessible_when_not_installed(): void
    {
        $this->get('/install/requirements')->assertStatus(200);
    }

    public function test_installer_database_step_accessible(): void
    {
        $this->get('/install/database')->assertStatus(200);
    }

    public function test_installer_admin_step_accessible(): void
    {
        $this->get('/install/account')->assertStatus(200);
    }

    public function test_installer_settings_step_accessible(): void
    {
        $this->get('/install/settings')->assertStatus(200);
    }

    /**
     * No installer URL may contain "/admin". Cloudflare's managed rules and
     * many shared hosts block such paths before they reach PHP, which 403'd
     * this step with nothing in the log to explain it.
     */
    public function test_no_installer_route_uses_an_admin_path(): void
    {
        $paths = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'install'))
            ->map(fn ($route) => $route->uri());

        $this->assertNotEmpty($paths, 'Installer routes should be registered.');

        foreach ($paths as $path) {
            $this->assertStringNotContainsString('admin', $path, "Installer route [$path] uses a commonly blocked path.");
        }
    }

    public function test_installer_finish_step_accessible(): void
    {
        $this->get('/install/finish')->assertStatus(200);
    }

    // ── Guard: already installed ──────────────────────────────────────────────

    public function test_installer_redirects_to_admin_when_already_installed(): void
    {
        file_put_contents(storage_path('installed.lock'), 'installed');

        $this->get('/install')->assertRedirect('/admin/login');
    }

    // ── Guard: app routes redirect to installer when not installed ────────────

    public function test_admin_redirects_to_installer_when_not_installed(): void
    {
        $this->get('/admin')->assertRedirect('/install');
    }

    public function test_home_redirects_to_installer_when_not_installed(): void
    {
        $this->get('/')->assertRedirect('/install');
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_database_step_validates_required_fields(): void
    {
        $this->post('/install/database', [])->assertSessionHasErrors([
            'db_host', 'db_port', 'db_database', 'db_username',
        ]);
    }

    public function test_database_step_redirects_on_successful_connection(): void
    {
        $this->mock(InstallerService::class, function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('testDatabaseConnection')
                ->once()
                ->with('db', 3306, 'db', 'db', 'db')
                ->andReturnNull(); // null = connected
        });

        $this->post('/install/database', [
            'db_host' => 'db',
            'db_port' => 3306,
            'db_database' => 'db',
            'db_username' => 'db',
            'db_password' => 'db',
        ])->assertRedirect('/install/account');
    }

    public function test_database_step_returns_error_on_failed_connection(): void
    {
        $this->mock(InstallerService::class, function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('testDatabaseConnection')
                ->once()
                ->andReturn("Access denied for user 'wrong_user'@'localhost'");
        });

        $this->post('/install/database', [
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_database' => 'wrong_db',
            'db_username' => 'wrong_user',
            'db_password' => 'wrong_pass',
        ])->assertSessionHasErrors('db_host');

        // The driver's reason reaches the user — that is the whole point.
        $this->assertStringContainsString(
            'Access denied',
            session('errors')->first('db_host'),
        );
    }

    public function test_database_step_rejects_invalid_port(): void
    {
        $this->post('/install/database', [
            'db_host' => '127.0.0.1',
            'db_port' => 99999,
            'db_database' => 'hybridcore',
            'db_username' => 'root',
        ])->assertSessionHasErrors('db_port');
    }

    public function test_admin_step_validates_required_fields(): void
    {
        $this->post('/install/account', [])->assertSessionHasErrors([
            'name', 'email', 'password',
        ]);
    }

    public function test_admin_step_rejects_password_mismatch(): void
    {
        $this->post('/install/account', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
    }

    public function test_admin_step_rejects_short_password(): void
    {
        $this->post('/install/account', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_settings_step_validates_required_fields(): void
    {
        $this->post('/install/settings', [])->assertSessionHasErrors([
            'app_name', 'app_url', 'app_locale', 'app_timezone',
        ]);
    }

    public function test_settings_step_rejects_invalid_url(): void
    {
        $this->post('/install/settings', [
            'app_name' => 'Test',
            'app_url' => 'not-a-url',
            'app_locale' => 'en',
            'app_timezone' => 'UTC',
        ])->assertSessionHasErrors('app_url');
    }

    // ── Finish + lock file ────────────────────────────────────────────────────

    public function test_finish_complete_creates_lock_file_and_redirects(): void
    {
        // Mock the service so we don't need a real MySQL connection or env write
        $this->mock(InstallerService::class, function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('writeEnvValues')->once();
            $mock->shouldReceive('generateAppKey')->once();
            $mock->shouldReceive('runMigrations')->once();
        });

        $response = $this->withSession([
            'installer.database' => [
                'db_host' => '127.0.0.1',
                'db_port' => 3306,
                'db_database' => 'hybridcore_test',
                'db_username' => 'root',
                'db_password' => '',
            ],
            'installer.admin' => [
                'name' => 'Admin',
                'email' => 'admin@test.com',
                'password' => 'password123',
            ],
            'installer.settings' => [
                'app_name' => 'HybridCore Test',
                'app_url' => 'http://localhost',
                'app_locale' => 'en',
                'app_timezone' => 'UTC',
            ],
        ])->post('/install/finish');

        $this->assertFileExists(storage_path('installed.lock'));
        $response->assertRedirect('/admin');
    }

    public function test_finish_complete_redirects_to_welcome_if_session_incomplete(): void
    {
        $response = $this->post('/install/finish');
        $response->assertRedirect('/install');
    }
}
