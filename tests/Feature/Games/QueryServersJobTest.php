<?php

namespace Tests\Feature\Games;

use App\Games\Concurrent\A2SBatch;
use App\Games\Data\QueryResult;
use App\Jobs\QueryServerJob;
use App\Jobs\QueryServersJob;
use App\Models\Game;
use App\Models\Server;
use App\Models\ServerSnapshot;
use App\Services\ServerQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * The sweep must send Source-family servers through the concurrent batch and
 * leave the stateful protocols (Minecraft, FiveM) as their own jobs — that
 * split is the whole point of the optimisation.
 */
class QueryServersJobTest extends TestCase
{
    use RefreshDatabase;

    private function server(string $driver): Server
    {
        $game = Game::factory()->create(['query_driver' => $driver]);

        return Server::factory()->create(['game_id' => $game->id, 'is_active' => true]);
    }

    public function test_a2s_servers_are_queried_through_the_batch_and_recorded(): void
    {
        Queue::fake();

        $cs = $this->server('cs16');
        $rust = $this->server('rust');

        // Stand in for the network: the batch returns canned results keyed by id.
        $this->mock(A2SBatch::class, function (Mockery\MockInterface $m) use ($cs, $rust) {
            $m->shouldReceive('run')
                ->once()
                ->andReturn([
                    $cs->id => new QueryResult(online: true, name: 'CS Server', playersOnline: 5, playersMax: 20, ping: 30),
                    $rust->id => QueryResult::offline('No response (timed out)'),
                ]);
        });

        (new QueryServersJob)->handle(app(A2SBatch::class), app(ServerQueryService::class));

        $online = ServerSnapshot::where('server_id', $cs->id)->latest('id')->first();
        $this->assertTrue($online->is_online);
        $this->assertSame(5, $online->players_online);

        $offline = ServerSnapshot::where('server_id', $rust->id)->latest('id')->first();
        $this->assertFalse($offline->is_online);
        $this->assertSame('No response (timed out)', $offline->failure_reason);

        // No per-server jobs for the A2S servers — they went through the batch.
        Queue::assertNotPushed(QueryServerJob::class);
    }

    public function test_non_a2s_servers_are_dispatched_as_individual_jobs(): void
    {
        Queue::fake();

        $mc = $this->server('minecraft_java');
        $fivem = $this->server('fivem');

        // The batch is still asked to run, with an empty target set.
        $this->mock(A2SBatch::class, function (Mockery\MockInterface $m) {
            $m->shouldReceive('run')->andReturn([]);
        });

        (new QueryServersJob)->handle(app(A2SBatch::class), app(ServerQueryService::class));

        Queue::assertPushed(QueryServerJob::class, 2);
    }

    public function test_inactive_servers_are_skipped(): void
    {
        Queue::fake();

        $game = Game::factory()->create(['query_driver' => 'cs16']);
        Server::factory()->create(['game_id' => $game->id, 'is_active' => false]);

        $this->mock(A2SBatch::class, function (Mockery\MockInterface $m) {
            // An empty target set means no active A2S servers were included.
            $m->shouldReceive('run')->with([])->andReturn([]);
        });

        (new QueryServersJob)->handle(app(A2SBatch::class), app(ServerQueryService::class));

        $this->assertSame(0, ServerSnapshot::count());
    }
}
