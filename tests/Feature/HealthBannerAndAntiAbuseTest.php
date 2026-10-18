<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Server;
use App\Models\ServerConnectionClick;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthBannerAndAntiAbuseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    public function test_health_check_artisan_command_returns_success_and_json(): void
    {
        $this->artisan('core:health')
            ->assertExitCode(0);

        $this->artisan('core:health --json')
            ->expectsOutputToContain('"checks":')
            ->assertExitCode(0);
    }

    public function test_announcement_banner_is_shared_with_inertia_props(): void
    {
        $settings = app(SettingsService::class);
        $settings->setMany([
            'announcement_banner_enabled' => '1',
            'announcement_banner_text' => 'Maintenance planned for 20:00 UTC',
            'announcement_banner_type' => 'warning',
            'announcement_banner_link' => 'https://hybridmind.net/status',
        ]);

        $response = $this->get('/');
        $response->assertOk();

        $page = $response->viewData('page');
        $this->assertNotNull($page['props']['announcementBanner'] ?? null);
        $this->assertSame('Maintenance planned for 20:00 UTC', $page['props']['announcementBanner']['text']);
        $this->assertSame('warning', $page['props']['announcementBanner']['type']);
        $this->assertSame('https://hybridmind.net/status', $page['props']['announcementBanner']['link']);
    }

    public function test_server_connect_clicks_are_deduplicated_per_ip_within_one_hour(): void
    {
        $game = Game::create([
            'name' => 'Counter-Strike 2',
            'slug' => 'cs2',
            'query_driver' => 'source',
            'default_port' => 27015,
        ]);

        $server = Server::create([
            'game_id' => $game->id,
            'name' => 'CS2 Retake #1',
            'ip' => '127.0.0.1',
            'port' => 27015,
            'is_active' => true,
        ]);

        // First click from client IP
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.19'])
            ->get(route('servers.connect', ['game' => $game->slug, 'ip' => $server->ip, 'port' => $server->port]))
            ->assertRedirect('steam://connect/127.0.0.1:27015');

        $this->assertSame(1, ServerConnectionClick::where('server_id', $server->id)->count());

        // Second click within same hour from same IP should not create duplicate click record
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.19'])
            ->get(route('servers.connect', ['game' => $game->slug, 'ip' => $server->ip, 'port' => $server->port]))
            ->assertRedirect('steam://connect/127.0.0.1:27015');

        $this->assertSame(1, ServerConnectionClick::where('server_id', $server->id)->count());

        // Click from a different IP should be recorded
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->get(route('servers.connect', ['game' => $game->slug, 'ip' => $server->ip, 'port' => $server->port]))
            ->assertRedirect('steam://connect/127.0.0.1:27015');

        $this->assertSame(2, ServerConnectionClick::where('server_id', $server->id)->count());
    }
}
