<?php

namespace App\Games\Drivers;

use App\Games\Contracts\GameDriver;
use App\Games\Data\PlayerData;
use App\Games\Data\QueryResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * FiveM / RedM (CitizenFX). These expose plain HTTP JSON endpoints on the game
 * port — info.json for the server, players.json for the roster — so there is no
 * binary protocol involved.
 */
final class FiveMDriver implements GameDriver
{
    public static function handles(): array
    {
        return ['fivem', 'redm', 'cfx'];
    }

    public function query(string $host, int $port, float $timeout = 3.0): QueryResult
    {
        $base = "http://{$host}:{$port}";

        try {
            $startedAt = microtime(true);

            $info = Http::timeout((int) ceil($timeout))
                ->connectTimeout((int) ceil($timeout))
                ->get("{$base}/info.json")
                ->throw()
                ->json();

            $ping = (int) round((microtime(true) - $startedAt) * 1000);

            if (! is_array($info)) {
                return QueryResult::offline('info.json was not valid JSON');
            }

            $roster = Http::timeout((int) ceil($timeout))->get("{$base}/players.json")->json();
            $roster = is_array($roster) ? $roster : [];

            $players = [];
            foreach ($roster as $p) {
                $name = $this->clean((string) ($p['name'] ?? ''));
                if ($name !== '') {
                    $players[] = new PlayerData($name, (int) ($p['ping'] ?? 0), 0);
                }
            }

            $vars = $info['vars'] ?? [];

            return new QueryResult(
                online: true,
                name: $this->clean((string) ($vars['sv_projectName'] ?? $vars['sv_hostname'] ?? 'FiveM Server')),
                map: isset($vars['mapname']) ? $this->clean((string) $vars['mapname']) : null,
                playersOnline: count($roster),
                playersMax: (int) ($vars['sv_maxClients'] ?? $info['vars']['sv_maxclients'] ?? 0),
                ping: $ping,
                passwordProtected: ($vars['sv_scriptHookAllowed'] ?? null) === 'false' ? false : false,
                version: isset($info['version']) ? (string) $info['version'] : null,
                players: $players,
            );
        } catch (Throwable $e) {
            return QueryResult::offline($e->getMessage());
        }
    }

    private function clean(string $value): string
    {
        // FiveM names carry ^1..^9 colour codes and control characters.
        $value = preg_replace('/\^[0-9]/', '', $value) ?? $value;
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? $value;

        return mb_substr(trim($value), 0, 200);
    }
}
