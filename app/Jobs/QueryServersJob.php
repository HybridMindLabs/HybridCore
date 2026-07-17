<?php

namespace App\Jobs;

use App\Games\Concurrent\A2SBatch;
use App\Games\Drivers\SourceDriver;
use App\Models\Server;
use App\Services\ServerQueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Queries every active server each cycle.
 *
 * The Source/GoldSource servers — the large majority — go through A2SBatch,
 * which drives all their sockets at once, so the sweep finishes in about one
 * timeout no matter how many are down. Games that need a stateful protocol
 * (Minecraft, FiveM) are dispatched as individual jobs to run on the queue.
 */
class QueryServersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // The batch itself does the waiting; give it room over the query timeout.
    public int $timeout = 30;

    public int $tries = 1;

    public function handle(A2SBatch $batch, ServerQueryService $service): void
    {
        $servers = Server::active()->with('game')->get();
        $a2sSlugs = SourceDriver::handles();

        [$a2s, $other] = $servers->partition(
            fn (Server $s) => in_array((string) $s->game?->query_driver, $a2sSlugs, true),
        );

        // Concurrent path: one batch for every A2S server.
        if ($a2s->isNotEmpty()) {
            $targets = $a2s->mapWithKeys(fn (Server $s) => [
                $s->id => ['host' => $s->ip, 'port' => (int) ($s->query_port ?: $s->port)],
            ])->all();

            foreach ($batch->run($targets) as $serverId => $result) {
                $server = $a2s->firstWhere('id', $serverId);
                if ($server !== null) {
                    $service->record($server, $result);
                    Cache::forget('server.snapshot.'.$serverId);
                }
            }
        }

        // Everything else keeps its own stateful driver (and player list).
        $other->each(fn (Server $s) => QueryServerJob::dispatch($s));
    }
}
