<?php

namespace App\Games\Concurrent;

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
 * A per-server state machine walks INFO -> (challenge) -> parse. It never
 * throws; a server that misbehaves is simply marked offline with a reason.
 */
class A2SBatch
{
    private const INFO_PREFIX = "\xFF\xFF\xFF\xFFTSource Engine Query\x00";

    /**
     * @param  array<int|string, array{host: string, port: int}>  $targets  keyed by anything you want the results keyed by
     * @return array<int|string, QueryResult>
     */
    public function run(array $targets, float $timeout = 4.0): array
    {
        /** @var array<int|string, array{sock: resource, host: string, port: int, sentAt: float, stage: string}> $conns */
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

            $conns[$key] = ['sock' => $sock, 'host' => $t['host'], 'port' => $t['port'], 'sentAt' => microtime(true), 'stage' => 'info'];
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

                $conn = $conns[$key];
                $raw = @fread($sock, 65_535);

                if ($raw === false || $raw === '') {
                    continue;
                }

                $outcome = $this->handle($conn, $raw);

                if ($outcome instanceof QueryResult) {
                    $results[$key] = $outcome;
                    fclose($sock);
                    unset($conns[$key]);
                } else {
                    // A challenge came back; the socket was re-armed in handle().
                    $conns[$key] = $outcome;
                }
            }
        }

        // Anything still open never answered in time.
        foreach ($conns as $key => $conn) {
            $results[$key] = QueryResult::offline('No response (timed out)');
            fclose($conn['sock']);
        }

        return $results;
    }

    /**
     * Advance one connection. Returns a QueryResult when done, or the updated
     * connection state when it re-sent a challenge and needs another read.
     *
     * @param  array{sock: resource, host: string, port: int, sentAt: float, stage: string}  $conn
     * @return QueryResult|array{sock: resource, host: string, port: int, sentAt: float, stage: string}
     */
    private function handle(array $conn, string $raw): QueryResult|array
    {
        try {
            $buffer = new Buffer($raw);

            if ($buffer->readInt32() !== -1) {
                // Split responses are rare for INFO; treat as unsupported here
                // and let the per-server driver handle those edge cases.
                return QueryResult::offline('Split INFO response');
            }

            $type = $buffer->get(1);

            if ($type === 'A') {
                // Challenge: resend INFO with the token, once.
                if ($conn['stage'] === 'challenged') {
                    return QueryResult::offline('Repeated challenge');
                }
                $challenge = $buffer->get(4);
                fwrite($conn['sock'], self::INFO_PREFIX.$challenge);
                $conn['stage'] = 'challenged';

                return $conn;
            }

            $ping = (int) round((microtime(true) - $conn['sentAt']) * 1000);

            return match ($type) {
                'I' => $this->parseSource($buffer, $ping),
                'm' => $this->parseGoldSource($buffer, $ping),
                default => QueryResult::offline('Unexpected type 0x'.bin2hex($type)),
            };
        } catch (\Throwable $e) {
            return QueryResult::offline($e->getMessage());
        }
    }

    private function parseSource(Buffer $b, int $ping): QueryResult
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

        return new QueryResult(
            online: true, name: $name, map: $map,
            playersOnline: $players, playersMax: $max, ping: $ping,
            passwordProtected: $password, secure: $vac, version: $version,
        );
    }

    private function parseGoldSource(Buffer $b, int $ping): QueryResult
    {
        $b->readString();
        $name = $this->clean($b->readString());
        $map = $this->clean($b->readString());
        $b->readString();
        $b->readString();
        $players = $b->readUInt8();
        $max = $b->readUInt8();

        return new QueryResult(
            online: true, name: $name, map: $map,
            playersOnline: $players, playersMax: $max, ping: $ping,
        );
    }

    /** @param array<int|string, array{sock: resource, ...}> $conns */
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
