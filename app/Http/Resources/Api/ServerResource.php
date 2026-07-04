<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class ServerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'ip' => $this->ip,
            'port' => $this->port,
            'game' => $this->game
                ? ['id' => $this->game->id, 'name' => $this->game->name, 'icon' => $this->game->icon]
                : null,
            'players' => $this->latestSnapshot?->players_online,
            'max_players' => $this->latestSnapshot?->players_max ?? $this->max_players,
            'map' => $this->latestSnapshot?->map,
            'online' => $this->is_online,
            'connect_url' => $this->connect_url,
        ];
    }
}
