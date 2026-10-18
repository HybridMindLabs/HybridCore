<?php

namespace Tests\Feature;

use App\Events\ServerStatusUpdated;
use App\Games\Data\QueryResult;
use App\Models\Game;
use App\Models\Server;
use App\Services\ServerQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServerStatusBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_status_updated_event_is_broadcast_when_snapshot_is_recorded(): void
    {
        Event::fake([ServerStatusUpdated::class]);

        $game = Game::factory()->create();
        $server = Server::factory()->create([
            'game_id' => $game->id,
            'name' => 'Test CS Server',
            'is_active' => true,
        ]);

        $result = new QueryResult(
            online: true,
            name: 'Updated Server Name',
            map: 'de_dust2',
            playersOnline: 12,
            playersMax: 24,
            ping: 18,
            passwordProtected: false,
            secure: true,
            version: '1.0',
            players: [],
        );

        $service = app(ServerQueryService::class);
        $snapshot = $service->record($server, $result);

        Event::assertDispatched(ServerStatusUpdated::class, function (ServerStatusUpdated $event) use ($server, $snapshot) {
            $this->assertSame($server->id, $event->server->id);
            $this->assertSame($snapshot->id, $event->snapshot->id);

            $payload = $event->broadcastWith();
            $this->assertSame($server->id, $payload['id']);
            $this->assertSame('Updated Server Name', $payload['name']);
            $this->assertTrue($payload['is_online']);
            $this->assertSame(12, $payload['players_online']);
            $this->assertSame(24, $payload['players_max']);
            $this->assertSame('de_dust2', $payload['map']);
            $this->assertSame(18, $payload['ping']);

            $this->assertSame('server.updated', $event->broadcastAs());
            $this->assertSame('servers', $event->broadcastOn()[0]->name);

            return true;
        });
    }
}
