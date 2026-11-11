<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Server;
use App\Models\ServerCommand;
use App\Models\ServerSnapshot;
use App\Services\Bridge\BridgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HiddenServerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    public function test_hidden_server_is_not_displayed_in_public_index_and_excluded_from_stats(): void
    {
        $game = Game::factory()->create();

        // Visible server
        $visibleServer = Server::factory()->create([
            'game_id' => $game->id,
            'is_active' => true,
            'is_visible' => true,
        ]);
        ServerSnapshot::create([
            'server_id' => $visibleServer->id,
            'is_online' => true,
            'players_online' => 10,
            'players_max' => 32,
            'recorded_at' => now(),
        ]);

        // Hidden server (backend server behind BungeeCord/Velocity)
        $hiddenServer = Server::factory()->create([
            'game_id' => $game->id,
            'is_active' => true,
            'is_visible' => false,
        ]);
        ServerSnapshot::create([
            'server_id' => $hiddenServer->id,
            'is_online' => true,
            'players_online' => 50,
            'players_max' => 100,
            'recorded_at' => now(),
        ]);

        $response = $this->get(route('servers.index'));
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Web/Servers/Index')
            ->where('totals.servers', 1)
            ->where('totals.online_servers', 1)
            ->where('totals.players', 10)
            ->where('games.0.servers_count', 1)
            ->where('games.0.players_online', 10)
            ->where('games.0.online_servers', 1)
        );
    }

    public function test_hidden_server_is_not_displayed_in_public_game_browser(): void
    {
        $game = Game::factory()->create();

        $visibleServer = Server::factory()->create([
            'game_id' => $game->id,
            'name' => 'Public Survival',
            'is_active' => true,
            'is_visible' => true,
        ]);
        ServerSnapshot::create([
            'server_id' => $visibleServer->id,
            'is_online' => true,
            'players_online' => 5,
            'players_max' => 20,
            'recorded_at' => now(),
        ]);

        $hiddenServer = Server::factory()->create([
            'game_id' => $game->id,
            'name' => 'Backend Survival Node 1',
            'is_active' => true,
            'is_visible' => false,
        ]);
        ServerSnapshot::create([
            'server_id' => $hiddenServer->id,
            'is_online' => true,
            'players_online' => 40,
            'players_max' => 50,
            'recorded_at' => now(),
        ]);

        $response = $this->get(route('servers.game', $game->slug));
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Web/Servers/Game')
            ->has('servers', 1)
            ->where('servers.0.id', $visibleServer->id)
            ->where('servers.0.name', 'Public Survival')
            ->where('stats.total', 1)
            ->where('stats.online', 1)
            ->where('stats.players', 5)
        );
    }

    public function test_hidden_server_is_not_displayed_on_home_page(): void
    {
        $game = Game::factory()->create();

        $visibleServer = Server::factory()->create([
            'game_id' => $game->id,
            'is_active' => true,
            'is_visible' => true,
        ]);
        ServerSnapshot::create([
            'server_id' => $visibleServer->id,
            'is_online' => true,
            'players_online' => 7,
            'players_max' => 20,
            'recorded_at' => now(),
        ]);

        $hiddenServer = Server::factory()->create([
            'game_id' => $game->id,
            'is_active' => true,
            'is_visible' => false,
        ]);
        ServerSnapshot::create([
            'server_id' => $hiddenServer->id,
            'is_online' => true,
            'players_online' => 30,
            'players_max' => 64,
            'recorded_at' => now(),
        ]);

        $response = $this->get(route('home'));
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Web/Home')
            ->has('servers', 1)
            ->where('servers.0.id', $visibleServer->id)
            ->where('totalPlayers', 7)
            ->where('maxPlayers', 20)
            ->where('stats.servers', 1)
        );
    }

    public function test_hidden_server_is_not_reachable_by_direct_url_or_api(): void
    {
        $game = Game::factory()->create();

        $visibleServer = Server::factory()->create([
            'game_id' => $game->id,
            'is_active' => true,
            'is_visible' => true,
        ]);

        $hiddenServer = Server::factory()->create([
            'game_id' => $game->id,
            'is_active' => true,
            'is_visible' => false,
        ]);

        $this->get(route('servers.show', [$game->slug, $hiddenServer->ip, $hiddenServer->port]))->assertNotFound();
        $this->get(route('servers.connect', [$game->slug, $hiddenServer->ip, $hiddenServer->port]))->assertNotFound();
        $this->getJson("/api/v1/servers/{$hiddenServer->id}")->assertNotFound();

        $this->getJson('/api/v1/servers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleServer->id);
    }

    public function test_hidden_server_authenticates_and_receives_commands_via_bridge(): void
    {
        $game = Game::factory()->create();
        $bridge = app(BridgeService::class);

        // Hidden backend server
        $server = Server::factory()->create([
            'game_id' => $game->id,
            'is_active' => true,
            'is_visible' => false,
        ]);

        // Issue token
        $token = $bridge->issueToken($server);
        $this->assertNotEmpty($token);

        // Verify BridgeService::resolveServer resolves hidden server
        $resolved = $bridge->resolveServer($token);
        $this->assertNotNull($resolved);
        $this->assertSame($server->id, $resolved->id);

        // Queue command for this hidden server
        $command = $bridge->queue($server, 'say Welcome to the hidden backend server');
        $this->assertSame(ServerCommand::STATUS_PENDING, $command->status);

        // Poll commands via POST /api/bridge/poll
        $response = $this->postJson('/api/bridge/poll', [], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'commands' => [
                '*' => ['id', 'command'],
            ],
            'server' => ['id', 'name'],
        ]);

        $response->assertJsonPath('commands.0.id', $command->id);
        $response->assertJsonPath('commands.0.command', 'say Welcome to the hidden backend server');
    }

    public function test_display_address_accessor_and_format_server(): void
    {
        $game = Game::factory()->create();

        // Server with display_address
        $serverWithDisplay = Server::factory()->create([
            'game_id' => $game->id,
            'ip' => '10.0.0.5',
            'port' => 25565,
            'display_address' => 'play.community.net',
            'is_active' => true,
            'is_visible' => true,
        ]);

        // Server without display_address
        $serverDirect = Server::factory()->create([
            'game_id' => $game->id,
            'ip' => '10.0.0.6',
            'port' => 25566,
            'display_address' => null,
            'is_active' => true,
            'is_visible' => true,
        ]);

        $this->assertSame('play.community.net', $serverWithDisplay->address);
        $this->assertSame('10.0.0.6:25566', $serverDirect->address);

        $response = $this->get(route('servers.game', $game->slug));
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->has('servers', 2)
            ->where('servers.0.address', 'play.community.net')
            ->where('servers.1.address', '10.0.0.6:25566')
        );
    }
}
