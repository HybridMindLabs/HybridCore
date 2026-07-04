<?php

namespace App\Services;

use App\Models\Server;
use App\Models\ServerPlayer;
use App\Models\ServerSnapshot;
use App\Services\Extensions\Registries\HookRegistry;
use App\Support\Hooks;
use GameQ\GameQ;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ServerQueryService
{
    public function query(Server $server): ServerSnapshot
    {
        $driver = $server->game->query_driver;

        try {
            $result = match ($driver) {
                'fivem' => $this->queryFiveM($server),
                'minecraft_java' => $this->queryGameQ($server, 'minecraft'),
                'minecraft_bedrock' => $this->queryGameQ($server, 'minecraftpe'),
                default => $this->queryGameQ($server, $driver),
            };
        } catch (\Throwable $e) {
            Log::warning("Server query failed [{$server->address}]: {$e->getMessage()}");
            $result = ['is_online' => false];
        }

        $snapshot = ServerSnapshot::create([
            'server_id' => $server->id,
            'is_online' => $result['is_online'] ?? false,
            'name' => $result['name'] ?? null,
            'map' => $result['map'] ?? null,
            'players_online' => $result['players_online'] ?? 0,
            'players_max' => $result['players_max'] ?? 0,
            'ping' => $result['ping'] ?? null,
            'is_password_protected' => $result['is_password_protected'] ?? false,
            'vac_secured' => $result['vac_secured'] ?? false,
            'game_version' => $result['game_version'] ?? null,
            'recorded_at' => now(),
        ]);

        if (! empty($result['players'])) {
            $players = array_map(fn ($p) => [
                'snapshot_id' => $snapshot->id,
                'name' => $p['name'] ?? null,
                'score' => $p['score'] ?? 0,
                'duration' => $p['duration'] ?? 0,
            ], $result['players']);

            ServerPlayer::insert($players);
        }

        // Auto-fill server name on first successful query
        if ($result['is_online'] ?? false) {
            $updates = ['last_queried_at' => now()];

            if (! $server->name && ! empty($result['name'])) {
                $updates['name'] = $result['name'];
            }

            $server->update($updates);
        }

        app(HookRegistry::class)->fire(Hooks::SERVER_QUERIED, $server, $snapshot);

        return $snapshot;
    }

    private function queryGameQ(Server $server, string $driver): array
    {
        $gameq = new GameQ;
        $gameq->addServer([
            'type' => $driver,
            'host' => "{$server->ip}:{$server->port}",
        ]);
        $gameq->setOption('timeout', 5);

        $results = $gameq->process();
        $data = reset($results);

        if (empty($data) || ! ($data['gq_online'] ?? false)) {
            return ['is_online' => false];
        }

        $players = [];
        foreach ($data['players'] ?? [] as $p) {
            $players[] = [
                'name' => $p['name'] ?? $p['gq_name'] ?? null,
                'score' => (int) ($p['score'] ?? $p['frags'] ?? 0),
                'duration' => (int) ($p['time'] ?? $p['time_f'] ?? 0),
            ];
        }

        return [
            'is_online' => true,
            'name' => $data['gq_hostname'] ?? $data['hostname'] ?? null,
            'map' => $data['gq_mapname'] ?? $data['mapname'] ?? null,
            'players_online' => (int) ($data['gq_numplayers'] ?? count($players)),
            'players_max' => (int) ($data['gq_maxplayers'] ?? 0),
            'ping' => isset($data['gq_ping']) ? (int) $data['gq_ping'] : null,
            'is_password_protected' => (bool) ($data['gq_password'] ?? false),
            'vac_secured' => (bool) ($data['secure'] ?? false),
            'game_version' => $data['version'] ?? null,
            'players' => $players,
        ];
    }

    private function queryFiveM(Server $server): array
    {
        $base = "http://{$server->ip}:{$server->port}";

        $info = Http::timeout(5)->get("{$base}/info.json")->json();
        $playersData = Http::timeout(5)->get("{$base}/players.json")->json() ?? [];

        if (empty($info)) {
            return ['is_online' => false];
        }

        $players = array_map(fn ($p) => [
            'name' => $p['name'] ?? null,
            'score' => $p['ping'] ?? 0,
            'duration' => 0,
        ], $playersData);

        return [
            'is_online' => true,
            'name' => $info['vars']['sv_projectName'] ?? $info['server'] ?? null,
            'map' => null,
            'players_online' => count($playersData),
            'players_max' => $info['vars']['sv_maxClients'] ?? 32,
            'ping' => null,
            'is_password_protected' => false,
            'vac_secured' => false,
            'game_version' => $info['version'] ?? null,
            'players' => $players,
        ];
    }
}
