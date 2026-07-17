<?php

namespace App\Services;

use App\Games\Data\QueryResult;
use App\Games\GameDriverRegistry;
use App\Models\Server;
use App\Models\ServerPlayer;
use App\Models\ServerSnapshot;
use App\Services\Extensions\Registries\HookRegistry;
use App\Support\Hooks;

class ServerQueryService
{
    /** How long any single server query may take before it's given up on. */
    private const TIMEOUT = 4.0;

    public function __construct(private readonly GameDriverRegistry $drivers) {}

    public function query(Server $server): ServerSnapshot
    {
        return $this->record($server, $this->run($server));
    }

    /**
     * Persist a result as a snapshot and update the server. Public so the
     * concurrent batch path (A2SBatch) records through exactly the same code as
     * a single query — one place decides what a snapshot looks like.
     */
    public function record(Server $server, QueryResult $result): ServerSnapshot
    {
        $snapshot = ServerSnapshot::create([
            'server_id' => $server->id,
            'is_online' => $result->online,
            'failure_reason' => $result->online ? null : $result->failureReason,
            'name' => $result->name,
            'map' => $result->map,
            'players_online' => $result->playersOnline,
            'players_max' => $result->playersMax,
            'ping' => $result->ping,
            'is_password_protected' => $result->passwordProtected,
            'vac_secured' => $result->secure,
            'game_version' => $result->version,
            'recorded_at' => now(),
        ]);

        if ($result->players !== []) {
            ServerPlayer::insert(array_map(fn ($p) => [
                'snapshot_id' => $snapshot->id,
                'name' => $p->name,
                'score' => $p->score,
                'duration' => $p->duration,
            ], $result->players));
        }

        // Adopt the reported name on the first successful query.
        if ($result->online) {
            $updates = ['last_queried_at' => now()];

            if (! $server->name && $result->name) {
                $updates['name'] = $result->name;
            }

            $server->update($updates);
        }

        app(HookRegistry::class)->fire(Hooks::SERVER_QUERIED, $server, $snapshot);

        return $snapshot;
    }

    /** Resolve a driver and run it. A driver never throws — it reports offline. */
    private function run(Server $server): QueryResult
    {
        $slug = (string) $server->game?->query_driver;
        $driver = $this->drivers->driverFor($slug);

        if ($driver === null) {
            return QueryResult::offline("No driver for '{$slug}'");
        }

        // Fall back to the game port when no separate query port is set — right
        // for every Source-engine game, where the two are the same.
        $port = $server->query_port ?: $server->port;

        return $driver->query($server->ip, (int) $port, self::TIMEOUT);
    }
}
