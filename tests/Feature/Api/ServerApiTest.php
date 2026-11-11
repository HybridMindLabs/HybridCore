<?php

namespace Tests\Feature\Api;

use App\Models\Server;
use App\Models\ServerPlayer;
use App\Models\ServerSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_servers_are_not_exposed(): void
    {
        $active = Server::factory()->create(['is_active' => true]);
        $inactive = Server::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/servers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);

        $this->getJson("/api/v1/servers/{$active->id}")->assertOk();
        $this->getJson("/api/v1/servers/{$inactive->id}")->assertNotFound();
    }

    public function test_players_are_listed_from_the_latest_snapshot_by_score(): void
    {
        $server = Server::factory()->create();
        ServerSnapshot::create(['server_id' => $server->id, 'is_online' => true, 'players_online' => 9, 'recorded_at' => now()->subMinutes(5)]);
        $latest = ServerSnapshot::create(['server_id' => $server->id, 'is_online' => true, 'players_online' => 2, 'players_max' => 32, 'recorded_at' => now()]);
        ServerPlayer::insert([
            ['snapshot_id' => $latest->id, 'name' => 'low', 'score' => 1, 'duration' => 60],
            ['snapshot_id' => $latest->id, 'name' => 'high', 'score' => 20, 'duration' => 600],
        ]);

        $this->getJson("/api/v1/servers/{$server->id}/players")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'high')
            ->assertJsonPath('meta.online', true)
            ->assertJsonPath('meta.players', 2)
            ->assertJsonPath('meta.max_players', 32);
    }

    public function test_players_of_an_offline_server_are_empty(): void
    {
        $server = Server::factory()->create();
        $snapshot = ServerSnapshot::create(['server_id' => $server->id, 'is_online' => false, 'recorded_at' => now()]);
        ServerPlayer::insert([['snapshot_id' => $snapshot->id, 'name' => 'ghost', 'score' => 0, 'duration' => 0]]);

        $this->getJson("/api/v1/servers/{$server->id}/players")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.online', false);
    }

    public function test_players_of_a_hidden_server_are_not_found(): void
    {
        $server = Server::factory()->create(['is_visible' => false]);

        $this->getJson("/api/v1/servers/{$server->id}/players")->assertNotFound();
    }
}
