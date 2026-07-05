<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerEvent;
use App\Services\Bridge\BridgeService;
use App\Services\Extensions\Registries\BridgeEventRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeIngestTest extends TestCase
{
    use RefreshDatabase;

    private function server(): Server
    {
        return Server::factory()->create(['bridge_enabled' => true]);
    }

    public function test_ingest_stores_valid_events_and_dedups(): void
    {
        $server = $this->server();
        $service = app(BridgeService::class);

        $events = [
            ['id' => 'e1', 'type' => 'player.connect', 'at' => 1_700_000_000, 'data' => ['name' => 'A']],
            ['id' => 'e2', 'type' => 'player.kill', 'data' => ['weapon' => 'ak47']],
            ['id' => '', 'type' => 'bad'],                 // invalid id → skipped
            ['id' => 'e3', 'type' => str_repeat('x', 70)], // type too long → skipped
        ];

        $accepted = $service->ingest($server, $events);

        $this->assertEqualsCanonicalizing(['e1', 'e2'], $accepted);
        $this->assertSame(2, ServerEvent::where('server_id', $server->id)->count());

        // Re-sending the same batch accepts (so the plugin drops them) but stores nothing new.
        $again = $service->ingest($server, $events);
        $this->assertEqualsCanonicalizing(['e1', 'e2'], $again);
        $this->assertSame(2, ServerEvent::where('server_id', $server->id)->count());
    }

    public function test_ingest_dispatches_to_registered_handlers(): void
    {
        $server = $this->server();
        $seen = [];

        app(BridgeEventRegistry::class)->on('player.kill', function (Server $s, array $data) use (&$seen) {
            $seen[] = $data['weapon'] ?? null;
        });

        // Queue is sync in tests, so the dispatch job runs inline.
        app(BridgeService::class)->ingest($server, [
            ['id' => 'k1', 'type' => 'player.kill', 'data' => ['weapon' => 'awp']],
            ['id' => 'x1', 'type' => 'server.heartbeat', 'data' => ['players' => 5]],
        ]);

        $this->assertSame(['awp'], $seen);
    }

    public function test_prune_events_removes_old_rows(): void
    {
        $server = $this->server();

        ServerEvent::create(['server_id' => $server->id, 'event_id' => 'old', 'type' => 'x', 'data' => [], 'created_at' => now()->subDays(40)]);
        ServerEvent::create(['server_id' => $server->id, 'event_id' => 'new', 'type' => 'x', 'data' => [], 'created_at' => now()]);

        app(BridgeService::class)->pruneEvents();

        $this->assertSame(1, ServerEvent::count());
        $this->assertNotNull(ServerEvent::where('event_id', 'new')->first());
    }
}
