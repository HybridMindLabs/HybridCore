<?php

namespace App\Jobs;

use App\Models\Server;
use App\Services\Extensions\Registries\BridgeEventRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Dispatches a batch of ingested bridge events to the extension handlers
 * registered on the BridgeEventRegistry. Runs on the queue so ingestion stays
 * fast and a slow handler never blocks the game server's HTTP request.
 */
class ProcessBridgeEvents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<int, array{type: string, data: array<string, mixed>, at: int|null}>  $events
     */
    public function __construct(
        public int $serverId,
        public array $events,
    ) {}

    public function handle(BridgeEventRegistry $registry): void
    {
        $server = Server::find($this->serverId);
        if ($server === null) {
            return;
        }

        foreach ($this->events as $event) {
            $type = $event['type'] ?? '';
            if ($type === '' || ! $registry->hasListeners($type)) {
                continue;
            }

            $occurredAt = isset($event['at']) && is_int($event['at'])
                ? Carbon::createFromTimestamp($event['at'])
                : null;

            $registry->dispatch($server, $type, $event['data'] ?? [], $occurredAt);
        }
    }
}
