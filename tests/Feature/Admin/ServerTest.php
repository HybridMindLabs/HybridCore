<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\Server;
use App\Models\ServerSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        @mkdir(storage_path(), 0755, true);
        file_put_contents(storage_path('installed.lock'), 'installed');

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->game = Game::factory()->create();
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed.lock'));
        parent::tearDown();
    }

    public function test_index_requires_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.servers.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_index_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.servers.index'))
            ->assertOk();
    }

    public function test_store_creates_server(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.servers.store'), [
                'game_id' => $this->game->id,
                'ip' => '127.0.0.1',
                'port' => 27015,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('servers', ['ip' => '127.0.0.1', 'port' => 27015, 'game_id' => $this->game->id]);
    }

    public function test_store_requires_game_ip_port(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.servers.store'), [])
            ->assertSessionHasErrors(['game_id', 'ip', 'port']);
    }

    public function test_store_requires_valid_game(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.servers.store'), [
                'game_id' => 99999,
                'ip' => '127.0.0.1',
                'port' => 27015,
            ])
            ->assertSessionHasErrors('game_id');
    }

    public function test_store_rejects_invalid_port(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.servers.store'), [
                'game_id' => $this->game->id,
                'ip' => '127.0.0.1',
                'port' => 99999,
            ])
            ->assertSessionHasErrors('port');
    }

    public function test_store_rejects_duplicate_ip_and_port(): void
    {
        Server::factory()->create(['game_id' => $this->game->id, 'ip' => '5.5.5.5', 'port' => 27015]);

        $this->actingAs($this->admin)
            ->post(route('admin.servers.store'), [
                'game_id' => $this->game->id,
                'ip' => '5.5.5.5',
                'port' => 27015,
            ])
            ->assertSessionHasErrors('ip');
    }

    public function test_store_allows_same_ip_with_different_port(): void
    {
        Server::factory()->create(['game_id' => $this->game->id, 'ip' => '5.5.5.5', 'port' => 27015]);

        $this->actingAs($this->admin)
            ->post(route('admin.servers.store'), [
                'game_id' => $this->game->id,
                'ip' => '5.5.5.5',
                'port' => 27016,
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
    }

    public function test_update_rejects_duplicate_ip_and_port(): void
    {
        Server::factory()->create(['game_id' => $this->game->id, 'ip' => '5.5.5.5', 'port' => 27015]);
        $server = Server::factory()->create(['game_id' => $this->game->id, 'ip' => '6.6.6.6', 'port' => 27015]);

        $this->actingAs($this->admin)
            ->put(route('admin.servers.update', $server), [
                'game_id' => $this->game->id,
                'ip' => '5.5.5.5',
                'port' => 27015,
            ])
            ->assertSessionHasErrors('ip');
    }

    public function test_update_allows_keeping_its_own_ip_and_port(): void
    {
        $server = Server::factory()->create(['game_id' => $this->game->id, 'ip' => '5.5.5.5', 'port' => 27015]);

        $this->actingAs($this->admin)
            ->put(route('admin.servers.update', $server), [
                'game_id' => $this->game->id,
                'ip' => '5.5.5.5',
                'port' => 27015,
                'name' => 'Renamed',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
    }

    public function test_update_changes_server(): void
    {
        $server = Server::factory()->create(['game_id' => $this->game->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.servers.update', $server), [
                'game_id' => $this->game->id,
                'ip' => '10.0.0.1',
                'port' => 27016,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('servers', ['id' => $server->id, 'ip' => '10.0.0.1', 'port' => 27016]);
    }

    public function test_destroy_deletes_server(): void
    {
        $server = Server::factory()->create(['game_id' => $this->game->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.servers.destroy', $server))
            ->assertRedirect();

        $this->assertDatabaseMissing('servers', ['id' => $server->id]);
    }

    public function test_index_exposes_the_bridge_command_log(): void
    {
        $server = Server::factory()->create(['game_id' => $this->game->id]);
        $server->bridgeCommands()->create(['command' => 'hc_give_vip x 30d', 'source' => 'hybridcore/store', 'status' => 'pending']);

        $this->actingAs($this->admin)
            ->get(route('admin.servers.index'))
            ->assertInertia(fn ($page) => $page
                ->where('servers.data.0.commands.0.command', 'hc_give_vip x 30d')
                ->where('servers.data.0.commands.0.source', 'hybridcore/store')
                ->where('servers.data.0.commands.0.status', 'pending')
                ->where('servers.data.0.bridge.pending_count', 1)
            );
    }

    public function test_admin_can_cancel_a_pending_command(): void
    {
        $server = Server::factory()->create(['game_id' => $this->game->id]);
        $command = $server->bridgeCommands()->create(['command' => 'hc_ban x', 'source' => 'core', 'status' => 'pending']);

        $this->actingAs($this->admin)
            ->post(route('admin.servers.commands.cancel', [$server, $command]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $command->fresh()->status);
    }

    public function test_cannot_cancel_a_command_that_was_already_delivered(): void
    {
        $server = Server::factory()->create(['game_id' => $this->game->id]);
        $command = $server->bridgeCommands()->create(['command' => 'hc_ban x', 'source' => 'core', 'status' => 'delivered']);

        $this->actingAs($this->admin)
            ->post(route('admin.servers.commands.cancel', [$server, $command]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('delivered', $command->fresh()->status);
    }

    public function test_cannot_cancel_another_servers_command(): void
    {
        $serverA = Server::factory()->create(['game_id' => $this->game->id, 'ip' => '9.9.9.9']);
        $serverB = Server::factory()->create(['game_id' => $this->game->id, 'ip' => '9.9.9.8']);
        $command = $serverA->bridgeCommands()->create(['command' => 'hc_ban x', 'source' => 'core', 'status' => 'pending']);

        $this->actingAs($this->admin)
            ->post(route('admin.servers.commands.cancel', [$serverB, $command]))
            ->assertNotFound();
    }

    public function test_index_filters_by_game(): void
    {
        $other = Game::factory()->create();
        Server::factory()->create(['game_id' => $this->game->id, 'ip' => '1.1.1.1']);
        Server::factory()->create(['game_id' => $other->id, 'ip' => '2.2.2.2']);

        $this->actingAs($this->admin)
            ->get(route('admin.servers.index', ['game_id' => $this->game->id]))
            ->assertOk();
    }

    /**
     * The listing paginates 20 per page — stats must count every matching
     * server, not just the ones on the current page, or "Online now" silently
     * under-reports the moment there's more than one page.
     */
    public function test_stats_reflect_every_matching_server_not_just_the_current_page(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $server = Server::factory()->create(['game_id' => $this->game->id, 'ip' => "10.0.0.{$i}"]);
            ServerSnapshot::create([
                'server_id' => $server->id,
                'is_online' => $i < 22, // 22 online, 3 offline — spans both pages of a 20-per-page paginator
                'players_online' => $i < 22 ? 4 : 0,
                'players_max' => 32,
                'recorded_at' => now(),
            ]);
        }

        $this->actingAs($this->admin)
            ->get(route('admin.servers.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.total', 25)
                ->where('stats.online', 22)
                ->where('stats.players', 22 * 4)
            );
    }
}
