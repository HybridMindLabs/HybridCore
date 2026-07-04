<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\Server;
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

    public function test_index_filters_by_game(): void
    {
        $other = Game::factory()->create();
        Server::factory()->create(['game_id' => $this->game->id, 'ip' => '1.1.1.1']);
        Server::factory()->create(['game_id' => $other->id, 'ip' => '2.2.2.2']);

        $this->actingAs($this->admin)
            ->get(route('admin.servers.index', ['game_id' => $this->game->id]))
            ->assertOk();
    }
}
