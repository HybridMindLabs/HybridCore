<?php

namespace App\Games\Concurrent;

use App\Games\Data\PlayerData;
use App\Games\Data\QueryResult;
use App\Games\Support\Buffer;

/**
 * Queries many A2S (Source / GoldSource) servers at once.
 *
 * Every server's UDP socket is non-blocking and driven together through a single
 * stream_select loop, so the whole batch finishes in roughly one timeout rather
 * than the sum of them — twenty dead servers cost one wait, not twenty. This is
 * the fast path for the scheduler, where most servers are the Source family.
 *
 * Each connection runs a small state machine:
 *   INFO -> (challenge) -> parse info -> PLAYER -> (challenge) -> parse players
 * so the player list is collected concurrently too, not just the summary. It
 * never throws; a server that misbehaves is marked offline with a reason, and a
 * server that answers INFO but refuses the player list still counts as online.
 */
class A2SBatch
{
    private const INFO_PREFIX = "\xFF\xFF\xFF\xFFTSource Engine Query\x00";

    private const PLAYER_PREFIX = "\xFF\xFF\xFF\xFF\x55";

    /**
     * @param  array<int|string, array{host: string, port: int}>  $targets  keyed by whatever you want the results keyed by
     * @return array<int|string, QueryResult>
     */
    public function run(array $targets, float $timeout = 4.0): array
    {
        /** @var array<int|string, array<string, mixed>> $conns */
        $conns = [];
        $results = [];

        foreach ($targets as $key => $t) {
            $sock = @stream_socket_client(
                "udp://{$t['host']}:{$t['port']}",
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
            );

            if ($sock === false) {
                $results[$key] = QueryResult::offline("Cannot open socket — {$errstr}");

                continue;
            }

            stream_set_blocking($sock, false);
            fwrite($sock, self::INFO_PREFIX);

            $conns[$key] = ['sock' => $sock, 'sentAt' => microtime(true), 'stage' => 'info', 'info' => null];
        }

        $deadline = microtime(true) + $timeout + 1.0;

        while ($conns !== [] && microtime(true) < $deadline) {
            $read = array_map(fn ($c) => $c['sock'], $conns);
            $write = $except = null;

            $remaining = max(0.1, $deadline - microtime(true));
            $sec = (int) $remaining;
            $usec = (int) (($remaining - $sec) * 1_000_000);

            if (@stream_select($read, $write, $except, $sec, $usec) < 1) {
                continue;
            }

            foreach ($read as $sock) {
                $key = $this->keyOf($conns, $sock);
                if ($key === null) {
                    continue;
                }

                $raw = @fread($sock, 65_535);
                if ($raw === false || $raw === '') {
                    continue;
                }

                $outcome = $this->advance($conns[$key], $raw);

                if ($outcome instanceof QueryResult) {
                    $results[$key] = $outcome;
                    fclose($sock);
                    unset($conns[$key]);
                } else {
                    $conns[$key] = $outcome; // re-armed for another read
                }
            }
        }

        // Anything still open never finished in time. If INFO already arrived,
        // keep it and just drop the player list; otherwise it's offline.
        foreach ($conns as $key => $conn) {
            $results[$key] = $conn['info'] !== null
                ? $this->finalise($conn['info'], [])
                : QueryResult::offline('No response (timed out)');
            fclose($conn['sock']);
        }

        return $results;
    }

    /**
     * Advance one connection by one received packet. Returns a QueryResult when
     * finished, or the updated connection state when it re-sent a request.
     *
     * @param  array<string, mixed>  $conn
     * @return QueryResult|array<string, mixed>
     */
    private function advance(array $conn, string $raw): QueryResult|array
    {
        try {
            $buffer = new Buffer($raw);

            if ($buffer->readInt32() !== -1) {
                // Split responses are rare here; the info summary is what the
                // sweep needs, so if we already have it, finish with it.
                return $conn['info'] !== null
                    ? $this->finalise($conn['info'], [])
                    : QueryResult::offline('Split response');
            }

            $type = $buffer->get(1);

            return match ($conn['stage']) {
                'info', 'info_challenged' => $this->onInfo($conn, $buffer, $type),
                default => $this->onPlayers($conn, $buffer, $type),
            };
        } catch (\Throwable $e) {
            return $conn['info'] !== null
                ? $this->finalise($conn['info'], [])
                : QueryResult::offline($e->getMessage());
        }
    }

    /** @param array<string, mixed> $conn @return QueryResult|array<string, mixed> */
    private function onInfo(array $conn, Buffer $buffer, string $type): QueryResult|array
    {
        if ($type === 'A') {
            if ($conn['stage'] === 'info_challenged') {
                return QueryResult::offline('Repeated challenge');
            }
            fwrite($conn['sock'], self::INFO_PREFIX.$buffer->get(4));
            $conn['stage'] = 'info_challenged';

            return $conn;
        }

        $info = match ($type) {
            'I' => $this->parseSource($buffer),
            'm' => $this->parseGoldSource($buffer),
            default => null,
        };

        if ($info === null) {
            return QueryResult::offline('Unexpected INFO type 0x'.bin2hex($type));
        }

        $info['ping'] = (int) round((microtime(true) - $conn['sentAt']) * 1000);
        $conn['info'] = $info;

        // Move on to the player list — start with a challenge request.
        fwrite($conn['sock'], self::PLAYER_PREFIX."\xFF\xFF\xFF\xFF");
        $conn['stage'] = 'players';

        return $conn;
    }

    /** @param array<string, mixed> $conn @return QueryResult|array<string, mixed> */
    private function onPlayers(array $conn, Buffer $buffer, string $type): QueryResult|array
    {
        if ($type === 'A') {
            if ($conn['stage'] === 'players_challenged') {
                return $this->finalise($conn['info'], []); // give up on the list, keep info
            }
            fwrite($conn['sock'], self::PLAYER_PREFIX.$buffer->get(4));
            $conn['stage'] = 'players_challenged';

            return $conn;
        }

        if ($type !== 'D') {
            return $this->finalise($conn['info'], []);
        }

        return $this->finalise($conn['info'], $this->parsePlayers($buffer));
    }

    /** @return array<string, mixed> */
    private function parseSource(Buffer $b): array
    {
        $b->readUInt8();
        $name = $this->clean($b->readString());
        $map = $this->clean($b->readString());
        $b->readString();
        $b->readString();
        $b->readInt16();
        $players = $b->readUInt8();
        $max = $b->readUInt8();
        $b->readUInt8();
        $b->readUInt8();
        $b->readUInt8();
        $password = $b->readUInt8() === 1;
        $vac = $b->readUInt8() === 1;
        $version = $b->isEmpty() ? null : $this->clean($b->readString());

        return compact('name', 'map', 'players', 'max', 'password', 'vac', 'version');
    }

    /** @return array<string, mixed> */
    private function parseGoldSource(Buffer $b): array
    {
        $b->readString();
        $name = $this->clean($b->readString());
        $map = $this->clean($b->readString());
        $b->readString();
        $b->readString();
        $players = $b->readUInt8();
        $max = $b->readUInt8();

        return ['name' => $name, 'map' => $map, 'players' => $players, 'max' => $max, 'password' => false, 'vac' => false, 'version' => null];
    }

    /** @return list<PlayerData> */
    private function parsePlayers(Buffer $b): array
    {
        $count = $b->readUInt8();
        $players = [];

        for ($i = 0; $i < $count && ! $b->isEmpty(); $i++) {
            $b->readUInt8(); // index
            $name = $this->clean($b->readString());
            $score = $b->readInt32();
            $duration = (int) $b->readFloat32();

            if ($name !== '') {
                $players[] = new PlayerData($name, $score, max(0, $duration));
            }
        }

        return $players;
    }

    /**
     * @param  array<string, mixed>  $info
     * @param  list<PlayerData>  $players
     */
    private function finalise(array $info, array $players): QueryResult
    {
        return new QueryResult(
            online: true,
            name: $info['name'],
            map: $info['map'],
            playersOnline: $info['players'],
            playersMax: $info['max'],
            ping: $info['ping'] ?? null,
            passwordProtected: $info['password'],
            secure: $info['vac'],
            version: $info['version'],
            players: $players,
        );
    }

    /** @param array<int|string, array<string, mixed>> $conns */
    private function keyOf(array $conns, mixed $sock): int|string|null
    {
        foreach ($conns as $key => $conn) {
            if ($conn['sock'] === $sock) {
                return $key;
            }
        }

        return null;
    }

    private function clean(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? $value;

        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return mb_substr(trim($value), 0, 200);
    }
}
