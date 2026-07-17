<?php

namespace App\Games\Drivers;

use App\Games\Contracts\GameDriver;
use App\Games\Data\PlayerData;
use App\Games\Data\QueryResult;
use App\Games\Support\Buffer;
use App\Games\Support\UdpSocket;
use Throwable;

/**
 * Valve's A2S protocol — every Source and GoldSource game: CS2, CS 1.6, Rust,
 * Garry's Mod, TF2, ARK, and the rest.
 *
 * Handles the parts the old library did not:
 *   - the A2S challenge (since 2020, servers reply 'A' + a token and expect the
 *     query resent with it; this is why servers "sometimes" failed),
 *   - split multi-packet responses, with bzip2 for GoldSource,
 *   - both the Source ('I') and GoldSource ('m') INFO formats.
 */
final class SourceDriver implements GameDriver
{
    private const A2S_INFO_PREFIX = "\xFF\xFF\xFF\xFFTSource Engine Query\x00";

    private const A2S_INFO = 'I';        // 0x49 Source info

    private const A2S_INFO_GOLD = 'm';   // 0x6D GoldSource info

    private const A2S_PLAYER = 'D';      // 0x44 player list

    private const A2S_CHALLENGE = 'A';   // 0x41 "resend with this token"

    public static function handles(): array
    {
        return [
            'source', 'goldsource',
            'cs2', 'cs16', 'css', 'csgo',
            'rust', 'gmod', 'tf2', 'hl2dm', 'dods', 'l4d2',
            'arkse', 'sevendaystodie', 'unturned', 'valheim', 'squad', 'insurgency',
        ];
    }

    public function query(string $host, int $port, float $timeout = 3.0): QueryResult
    {
        $socket = new UdpSocket($host, $port, $timeout);

        try {
            $startedAt = microtime(true);
            $info = $this->requestInfo($socket);
            $ping = (int) round((microtime(true) - $startedAt) * 1000);

            $players = $this->requestPlayers($socket, $host, $port);

            return new QueryResult(
                online: true,
                name: $info['name'],
                map: $info['map'],
                playersOnline: $info['players'],
                playersMax: $info['maxplayers'],
                ping: $ping,
                passwordProtected: $info['password'],
                secure: $info['vac'],
                version: $info['version'],
                players: $players,
            );
        } catch (Throwable $e) {
            return QueryResult::offline($e->getMessage());
        } finally {
            $socket->close();
        }
    }

    /**
     * A2S_INFO, following a challenge if the server asks for one.
     *
     * @return array{name: string, map: string, players: int, maxplayers: int, password: bool, vac: bool, version: ?string}
     */
    private function requestInfo(UdpSocket $socket): array
    {
        $buffer = $this->read($socket, self::A2S_INFO_PREFIX);
        $type = $buffer->get(1);

        if ($type === self::A2S_CHALLENGE) {
            $challenge = $buffer->get(4);
            $buffer = $this->read($socket, self::A2S_INFO_PREFIX.$challenge);
            $type = $buffer->get(1);
        }

        return match ($type) {
            self::A2S_INFO => $this->parseSourceInfo($buffer),
            self::A2S_INFO_GOLD => $this->parseGoldSourceInfo($buffer),
            default => throw new \RuntimeException('Unexpected INFO response type 0x'.bin2hex($type)),
        };
    }

    /** @return array{name: string, map: string, players: int, maxplayers: int, password: bool, vac: bool, version: ?string} */
    private function parseSourceInfo(Buffer $b): array
    {
        $b->readUInt8();                 // protocol
        $name = $this->clean($b->readString());
        $map = $this->clean($b->readString());
        $b->readString();                // folder
        $b->readString();                // game
        $b->readInt16();                 // appid
        $players = $b->readUInt8();
        $maxplayers = $b->readUInt8();
        $b->readUInt8();                 // bots
        $b->readUInt8();                 // server type
        $b->readUInt8();                 // environment
        $password = $b->readUInt8() === 1;
        $vac = $b->readUInt8() === 1;
        $version = $b->isEmpty() ? null : $this->clean($b->readString());

        return compact('name', 'map', 'players', 'maxplayers', 'password', 'vac', 'version');
    }

    /** @return array{name: string, map: string, players: int, maxplayers: int, password: bool, vac: bool, version: ?string} */
    private function parseGoldSourceInfo(Buffer $b): array
    {
        $b->readString();                // address
        $name = $this->clean($b->readString());
        $map = $this->clean($b->readString());
        $b->readString();                // folder
        $b->readString();                // game
        $players = $b->readUInt8();
        $maxplayers = $b->readUInt8();
        $b->readUInt8();                 // protocol
        $b->readUInt8();                 // server type
        $b->readUInt8();                 // environment
        $password = $b->readUInt8() === 1;

        return [
            'name' => $name,
            'map' => $map,
            'players' => $players,
            'maxplayers' => $maxplayers,
            'password' => $password,
            'vac' => false,
            'version' => null,
        ];
    }

    /**
     * A2S_PLAYER. Best-effort: a server can refuse the player list while still
     * being online, so a failure here yields an empty list, not an offline
     * result.
     *
     * @return list<PlayerData>
     */
    private function requestPlayers(UdpSocket $socket, string $host, int $port): array
    {
        try {
            // Player queries always start with a challenge request (-1 token).
            $buffer = $this->read($socket, "\xFF\xFF\xFF\xFF\x55\xFF\xFF\xFF\xFF");
            $type = $buffer->get(1);

            if ($type === self::A2S_CHALLENGE) {
                $challenge = $buffer->get(4);
                $buffer = $this->read($socket, "\xFF\xFF\xFF\xFF\x55".$challenge);
                $type = $buffer->get(1);
            }

            if ($type !== self::A2S_PLAYER) {
                return [];
            }

            $count = $buffer->readUInt8();
            $players = [];

            for ($i = 0; $i < $count && ! $buffer->isEmpty(); $i++) {
                $buffer->readUInt8();     // index (always 0 in practice)
                $name = $this->clean($buffer->readString());
                $score = $buffer->readInt32();
                $duration = (int) $buffer->readFloat32();

                if ($name !== '') {
                    $players[] = new PlayerData($name, $score, max(0, $duration));
                }
            }

            return $players;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Send a payload and return the reply as a Buffer positioned just past the
     * 4-byte header, reassembling a split response first if needed.
     */
    private function read(UdpSocket $socket, string $payload): Buffer
    {
        $raw = $socket->exchange($payload);
        $header = new Buffer($raw);
        $format = $header->readInt32();

        // -1 (0xFFFFFFFF) single packet; -2 (0xFFFFFFFE) split across datagrams.
        if ($format === -1) {
            return $header;
        }

        if ($format === -2) {
            return new Buffer($this->reassemble($socket, $raw));
        }

        throw new \RuntimeException('Unrecognised packet header');
    }

    /**
     * Collect and order the datagrams of a split response, then strip the
     * common 4-byte header. bzip2 (GoldSource) is decompressed when flagged.
     */
    private function reassemble(UdpSocket $socket, string $first): string
    {
        $packets = [];
        $expected = null;
        $compressed = false;
        $current = $first;

        do {
            $b = new Buffer($current);
            $b->get(4);                  // -2 header
            $requestId = $b->readInt32();
            $total = $b->readUInt8();
            $index = $b->readUInt8();
            $b->readInt16();             // max packet size

            // High bit of the request id flags bzip2 (GoldSource split).
            if ($index === 0 && ($requestId & 0x80000000)) {
                $compressed = true;
                $b->readInt32();         // uncompressed size
                $b->readInt32();         // crc32
            }

            $packets[$index] = $b->getRest();
            $expected ??= $total;

            if (count($packets) >= $expected) {
                break;
            }

            $current = $socket->receive();
        } while (count($packets) < $expected && count($packets) < 32);

        ksort($packets);
        $payload = implode('', $packets);

        if ($compressed && function_exists('bzdecompress')) {
            $payload = (string) bzdecompress($payload);
        }

        // The reassembled payload carries its own single-packet -1 header.
        $b = new Buffer($payload);
        $b->readInt32();

        return substr($payload, 4);
    }

    /**
     * Strip control bytes and cap length: server names are attacker-controlled
     * and end up in the database and the page. Colour codes and stray control
     * characters go; the visible text stays.
     */
    private function clean(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? $value;

        // Invalid UTF-8 (some GoldSource servers) would break JSON encoding.
        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        return mb_substr(trim($value), 0, 200);
    }
}
