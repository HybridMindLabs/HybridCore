<?php

namespace App\Events;

use App\Models\Server;
use App\Models\ServerSnapshot;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Server $server,
        public readonly ServerSnapshot $snapshot,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('servers')];
    }

    public function broadcastAs(): string
    {
        return 'server.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->server->id,
            'name' => $this->snapshot->name ?? $this->server->name,
            'is_online' => (bool) $this->snapshot->is_online,
            'players_online' => (int) ($this->snapshot->players_online ?? 0),
            'players_max' => (int) ($this->snapshot->players_max ?? 0),
            'map' => $this->snapshot->map ?? '—',
            'ping' => $this->snapshot->ping,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
