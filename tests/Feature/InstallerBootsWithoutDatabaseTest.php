<?php

namespace Tests\Feature;

use App\Services\Installer\InstallationStateService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A fresh checkout has no database yet — the installer is the page that
 * configures it. So the installer, and the middleware stack it runs through,
 * must survive a completely unreachable database. Regression cover for the
 * original failure: a raw QueryException on /install instead of the installer.
 *
 * Deliberately no RefreshDatabase — these tests want a broken connection.
 */
class InstallerBootsWithoutDatabaseTest extends TestCase
{
    /** Point the default connection at a database that cannot be reached. */
    private function breakDatabase(): void
    {
        config([
            'database.default' => 'unreachable',
            'database.connections.unreachable' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => 'hybridcore_does_not_exist',
                'username' => 'nobody',
                'password' => 'wrong',
            ],
        ]);

        DB::purge('unreachable');
    }

    /** Pretend the app has not been installed, whatever the real markers say. */
    private function pretendNotInstalled(): void
    {
        $this->mock(
            InstallationStateService::class,
            fn ($mock) => $mock->shouldReceive('isInstalled')->andReturnFalse(),
        );
    }

    public function test_installer_renders_when_the_database_is_unreachable(): void
    {
        $this->pretendNotInstalled();
        $this->breakDatabase();

        $this->get('/install')->assertOk();
    }

    public function test_requirements_step_renders_when_the_database_is_unreachable(): void
    {
        $this->pretendNotInstalled();
        $this->breakDatabase();

        $this->get('/install/requirements')->assertOk();
    }

    public function test_database_step_renders_when_the_database_is_unreachable(): void
    {
        $this->pretendNotInstalled();
        $this->breakDatabase();

        $this->get('/install/database')->assertOk();
    }

    public function test_page_view_tracking_never_breaks_a_request(): void
    {
        $this->breakDatabase();

        // The analytics middleware reads the page_views table; with the
        // connection down it must swallow the failure, not surface a 500.
        $this->get('/install')->assertStatus(200);
    }
}
