<?php

namespace App\Http\Resources\Api;

use App\Models\Server;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Server $server */
        $server = $this->resource;

        return [
            'id' => $server->id,
            'name' => $server->name,
            'ip' => $server->ip,
            'port' => $server->port,
            'game' => ['id' => $server->game->id, 'name' => $server->game->name, 'icon' => $server->game->icon],
            'players' => $server->latestSnapshot?->players_online,
            'max_players' => $server->latestSnapshot?->players_max,
            'map' => $server->latestSnapshot?->map,
            'online' => $server->is_online,
            // Was previously reading a non-existent $server->connect_url property,
            // so the public API always returned null here.
            'connect_url' => route('servers.connect', [$server->game->slug, $server->ip, $server->port]),
        ];
    }
}
