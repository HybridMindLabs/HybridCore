<?php

namespace App\Services\Extensions\Registries;

use App\Models\Server;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Lets extensions consume telemetry/events sent up from game servers through
 * the bridge ("in" direction). Handlers are keyed by event type; the special
 * type "*" receives every event.
 *
 *   $registry->bridgeEvents()->on('player.kill', function (Server $server, array $data, ?CarbonInterface $at) {
 *       // record the kill
 *   });
 *
 * The core dispatches ingested events (from a queued job) to matching handlers.
 * A handler that throws is logged and skipped — one bad handler must not drop
 * the whole batch.
 */
class BridgeEventRegistry
{
    /** @var array<string, array<int, Closure>> */
    private array $listeners = [];

    /**
     * @param  string  $type  Event type, or "*" for all events
     * @param  Closure(Server, array<string, mixed>, ?CarbonInterface): void  $handler
     */
    public function on(string $type, Closure $handler): void
    {
        $this->listeners[$type][] = $handler;
    }

    /** Dispatch one event to every matching handler. */
    public function dispatch(Server $server, string $type, array $data, ?CarbonInterface $occurredAt = null): void
    {
        $handlers = array_merge(
            $this->listeners[$type] ?? [],
            $this->listeners['*'] ?? [],
        );

        foreach ($handlers as $handler) {
            try {
                $handler($server, $data, $occurredAt);
            } catch (\Throwable $e) {
                Log::warning('Bridge event handler failed', [
                    'type' => $type,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    public function hasListeners(string $type): bool
    {
        return ! empty($this->listeners[$type]) || ! empty($this->listeners['*']);
    }

    /** @return array<int, string> */
    public function types(): array
    {
        return array_keys($this->listeners);
    }
}
