<?php

namespace App\Services\Bridge;

use App\Jobs\ProcessBridgeEvents;
use App\Models\Server;
use App\Models\ServerCommand;
use App\Models\ServerEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Game-server bridge: lets an in-game plugin (AMXX, SourceMod, …) securely
 * pull commands queued by the site (vote rewards, store purchases, bans).
 *
 * Security model:
 *  - One bearer token per server, 40 bytes of entropy, prefixed "hcb_".
 *  - Only the SHA-256 of the token is stored; the plain value is shown once.
 *  - The plugin polls over HTTPS; commands are delivered at-least-once and
 *    confirmed with an explicit ack. Unacked deliveries are retried, expired
 *    commands are never delivered.
 *
 * Extensions queue commands via:
 *
 *   app(BridgeService::class)->queue($server, "hc_give_vip {$steamId} 30d", 'hybridcore/store');
 */
class BridgeService
{
    /** Max commands handed out per poll. */
    public const PULL_LIMIT = 25;

    /** Default command lifetime. */
    public const DEFAULT_TTL_MINUTES = 24 * 60;

    /** Deliveries without an ack before a command is considered failed. */
    public const MAX_ATTEMPTS = 5;

    /** Max events accepted per ingest call. */
    public const INGEST_LIMIT = 100;

    /** How long raw events are kept before pruning (days). */
    public const EVENT_RETENTION_DAYS = 30;

    // ── Tokens ───────────────────────────────────────────────────

    /**
     * Generate (or rotate) the server's bridge token.
     * Returns the plain token — the only time it is ever available.
     */
    public function issueToken(Server $server): string
    {
        $plain = 'hcb_'.Str::random(40);

        $server->forceFill([
            'bridge_token_hash' => hash('sha256', $plain),
            'bridge_enabled' => true,
        ])->save();

        return $plain;
    }

    public function revokeToken(Server $server): void
    {
        $server->forceFill([
            'bridge_token_hash' => null,
            'bridge_enabled' => false,
        ])->save();
    }

    /** Resolve a bearer token to its server, or null when invalid/disabled. */
    public function resolveServer(?string $bearer): ?Server
    {
        if (! is_string($bearer) || ! str_starts_with($bearer, 'hcb_') || strlen($bearer) > 100) {
            return null;
        }

        return Server::where('bridge_token_hash', hash('sha256', $bearer))
            ->where('bridge_enabled', true)
            ->where('is_active', true)
            ->first();
    }

    // ── Queueing (called by core + extensions) ───────────────────

    public function queue(
        Server $server,
        string $command,
        string $source = 'core',
        int $ttlMinutes = self::DEFAULT_TTL_MINUTES,
    ): ServerCommand {
        $command = trim($command);

        if ($command === '' || mb_strlen($command) > 500) {
            throw new InvalidArgumentException('Bridge command must be 1–500 characters.');
        }

        // Newlines/control characters could smuggle extra console commands
        // into the game server — reject instead of sanitising silently.
        if (preg_match('/[\x00-\x1F\x7F]/', $command) === 1) {
            throw new InvalidArgumentException('Bridge command contains control characters.');
        }

        return ServerCommand::create([
            'server_id' => $server->id,
            'command' => $command,
            'source' => mb_substr($source, 0, 64),
            'status' => ServerCommand::STATUS_PENDING,
            'expires_at' => now()->addMinutes(max(1, $ttlMinutes)),
        ]);
    }

    // ── Polling protocol ─────────────────────────────────────────

    /**
     * Hand out the next batch for this server and mark it delivered.
     * Also re-delivers commands whose previous delivery was never acked.
     *
     * @return Collection<int, ServerCommand>
     */
    public function pull(Server $server): Collection
    {
        return DB::transaction(function () use ($server) {
            // Expire what's past its TTL so it can never be delivered.
            ServerCommand::where('server_id', $server->id)
                ->whereIn('status', [ServerCommand::STATUS_PENDING, ServerCommand::STATUS_DELIVERED])
                ->where('expires_at', '<', now())
                ->update(['status' => ServerCommand::STATUS_EXPIRED]);

            $batch = ServerCommand::where('server_id', $server->id)
                ->where(function ($q) {
                    $q->where('status', ServerCommand::STATUS_PENDING)
                        // Redeliver unacked batches after a grace period.
                        ->orWhere(fn ($sub) => $sub
                            ->where('status', ServerCommand::STATUS_DELIVERED)
                            ->where('delivered_at', '<', now()->subMinutes(2)));
                })
                ->where('attempts', '<', self::MAX_ATTEMPTS)
                ->orderBy('id')
                ->limit(self::PULL_LIMIT)
                ->lockForUpdate()
                ->get();

            foreach ($batch as $command) {
                $command->update([
                    'status' => ServerCommand::STATUS_DELIVERED,
                    'delivered_at' => now(),
                    'attempts' => $command->attempts + 1,
                ]);
            }

            return $batch;
        });
    }

    /**
     * Confirm execution of delivered commands.
     *
     * @param  array<int, int>  $ids
     * @return int Number of commands acknowledged.
     */
    public function ack(Server $server, array $ids): int
    {
        return ServerCommand::where('server_id', $server->id)
            ->whereIn('id', array_filter($ids, 'is_int'))
            ->where('status', ServerCommand::STATUS_DELIVERED)
            ->update(['status' => ServerCommand::STATUS_ACKED, 'acked_at' => now()]);
    }

    /** Purge finished/expired commands so the table stays small. */
    public function prune(): int
    {
        return ServerCommand::query()
            ->where(fn ($q) => $q
                ->whereIn('status', [ServerCommand::STATUS_ACKED, ServerCommand::STATUS_EXPIRED])
                ->where('updated_at', '<', now()->subDays(7)))
            ->orWhere('attempts', '>=', self::MAX_ATTEMPTS)
            ->delete();
    }

    // ── Event ingest ("in" direction) ────────────────────────────

    /**
     * Ingest a batch of telemetry/events reported by the game server. Stores
     * each new event (de-duplicated per server by its plugin-supplied id) into
     * a rolling log and queues them for dispatch to extension handlers.
     *
     * Invalid events are skipped, not fatal. Returns the ids that were accepted
     * so the plugin can drop them (at-least-once).
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, string> accepted event ids
     */
    public function ingest(Server $server, array $events): array
    {
        $events = array_slice($events, 0, self::INGEST_LIMIT);

        // Validate & normalise.
        $valid = [];
        foreach ($events as $event) {
            $normalised = $this->normaliseEvent($event);
            if ($normalised !== null) {
                $valid[$normalised['event_id']] = $normalised; // last one wins per id
            }
        }

        if ($valid === []) {
            return [];
        }

        // Drop ids we already have for this server (idempotent resend).
        $existing = ServerEvent::where('server_id', $server->id)
            ->whereIn('event_id', array_keys($valid))
            ->pluck('event_id')
            ->all();

        foreach ($existing as $id) {
            unset($valid[$id]);
        }

        // Even duplicates are "accepted" — the plugin should stop resending them.
        $acceptedIds = array_merge($existing, array_keys($valid));

        if ($valid === []) {
            return $acceptedIds;
        }

        $now = now();
        $rows = [];
        $forDispatch = [];

        foreach ($valid as $event) {
            $rows[] = [
                'server_id' => $server->id,
                'event_id' => $event['event_id'],
                'type' => $event['type'],
                'data' => json_encode($event['data']),
                'occurred_at' => $event['at'] ? Carbon::createFromTimestamp($event['at']) : null,
                'created_at' => $now,
            ];
            $forDispatch[] = ['type' => $event['type'], 'data' => $event['data'], 'at' => $event['at']];
        }

        // insertOrIgnore guards against a race with a concurrent duplicate poll.
        ServerEvent::insertOrIgnore($rows);

        ProcessBridgeEvents::dispatch($server->id, $forDispatch);

        return $acceptedIds;
    }

    /**
     * Validate one raw event → normalised shape, or null if invalid.
     *
     * @param  array<string, mixed>  $event
     * @return array{event_id: string, type: string, data: array<string, mixed>, at: int|null}|null
     */
    private function normaliseEvent(mixed $event): ?array
    {
        if (! is_array($event)) {
            return null;
        }

        $eventId = $event['id'] ?? null;
        $type = $event['type'] ?? null;

        if (! is_string($eventId) || $eventId === '' || mb_strlen($eventId) > 128) {
            return null;
        }
        if (! is_string($type) || $type === '' || mb_strlen($type) > 64) {
            return null;
        }
        // Control chars in the type could poison logs/queries.
        if (preg_match('/[\x00-\x1F\x7F]/', $type) === 1) {
            return null;
        }

        $data = $event['data'] ?? [];
        if (! is_array($data)) {
            return null;
        }
        // Cap the serialised payload to keep rows small.
        if (mb_strlen(json_encode($data) ?: '') > 8000) {
            return null;
        }

        $at = $event['at'] ?? null;
        $at = is_int($at) && $at > 0 ? $at : null;

        return ['event_id' => $eventId, 'type' => $type, 'data' => $data, 'at' => $at];
    }

    /** Purge old raw events so the table stays bounded. */
    public function pruneEvents(): int
    {
        return ServerEvent::where('created_at', '<', now()->subDays(self::EVENT_RETENTION_DAYS))->delete();
    }
}
