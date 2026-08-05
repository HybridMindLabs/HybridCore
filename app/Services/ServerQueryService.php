<?php

namespace App\Services;

use App\Games\Data\QueryResult;
use App\Games\GameDriverRegistry;
use App\Models\Server;
use App\Models\ServerPlayer;
use App\Models\ServerSnapshot;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\Extensions\Registries\HookRegistry;
use App\Support\Hooks;
use App\Support\HostSafety;
use Illuminate\Support\Facades\Notification;

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
        // Only the online→offline edge is worth an admin's attention — a
        // server that's already down would otherwise renotify every cycle.
        // Method-call form, not the ->latestSnapshot property: the property
        // caches on the model after first access, so a $server reused across
        // multiple record() calls (as every batch path does) would keep
        // reading the pre-batch snapshot instead of the one just written.
        $wasOnline = $server->latestSnapshot()->first()?->is_online ?? false;

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

        if ($wasOnline && ! $result->online) {
            Notification::send(
                User::where('is_admin', true)->get(),
                new SystemNotification(
                    message: "{$server->name} just went offline".($result->failureReason ? " ({$result->failureReason})" : '.'),
                    level: 'warning',
                    actionUrl: route('admin.servers.index'),
                    actionLabel: 'View servers',
                ),
            );
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

        if (! HostSafety::isSafePublicHost($server->ip)) {
            return QueryResult::offline('Host resolves to a private or reserved address');
        }

        // Fall back to the game port when no separate query port is set — right
        // for every Source-engine game, where the two are the same.
        $port = $server->query_port ?: $server->port;

        return $driver->query($server->ip, (int) $port, self::TIMEOUT);
    }
}
