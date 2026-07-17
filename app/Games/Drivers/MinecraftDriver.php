<?php

namespace App\Games\Drivers;

use App\Games\Contracts\GameDriver;
use App\Games\Data\QueryResult;
use Throwable;

/**
 * Minecraft Server List Ping (modern, 1.7+). A short TCP handshake over the
 * game port returns a JSON status blob — no server-side "enable-query" needed,
 * which is the usual reason Minecraft servers show as offline elsewhere.
 */
final class MinecraftDriver implements GameDriver
{
    public static function handles(): array
    {
        return ['minecraft', 'minecraft_java'];
    }

    public function query(string $host, int $port, float $timeout = 3.0): QueryResult
    {
        $socket = null;

        try {
            $startedAt = microtime(true);

            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
            if ($socket === false) {
                return QueryResult::offline("Cannot reach {$host}:{$port} — {$errstr}");
            }
            stream_set_timeout($socket, (int) $timeout);

            // Handshake (next state = 1, status) then an empty status request.
            $handshake = "\x00\x00".$this->varInt(strlen($host)).$host.pack('n', $port)."\x01";
            fwrite($socket, $this->varInt(strlen($handshake)).$handshake);
            fwrite($socket, "\x01\x00");

            $json = $this->readStatus($socket);
            $ping = (int) round((microtime(true) - $startedAt) * 1000);

            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);

            return new QueryResult(
                online: true,
                name: $this->motd($data['description'] ?? null),
                map: null,
                playersOnline: (int) ($data['players']['online'] ?? 0),
                playersMax: (int) ($data['players']['max'] ?? 0),
                ping: $ping,
                version: isset($data['version']['name']) ? $this->clean((string) $data['version']['name']) : null,
            );
        } catch (Throwable $e) {
            return QueryResult::offline($e->getMessage());
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    /** Read the length-prefixed status packet and return its JSON payload. */
    private function readStatus($socket): string
    {
        $this->readVarInt($socket);       // total packet length
        $this->readVarInt($socket);       // packet id (0x00)
        $length = $this->readVarInt($socket); // JSON string length

        if ($length <= 0 || $length > 262_144) {
            throw new \RuntimeException('Invalid status length');
        }

        $json = '';
        while (strlen($json) < $length) {
            $chunk = fread($socket, $length - strlen($json));
            if ($chunk === '' || $chunk === false) {
                break;
            }
            $json .= $chunk;
        }

        return $json;
    }

    private function varInt(int $value): string
    {
        $out = '';
        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            $out .= chr($value ? $byte | 0x80 : $byte);
        } while ($value);

        return $out;
    }

    private function readVarInt($socket): int
    {
        $value = 0;
        $shift = 0;
        do {
            $byte = fread($socket, 1);
            if ($byte === '' || $byte === false) {
                throw new \RuntimeException('Connection closed mid-VarInt');
            }
            $b = ord($byte);
            $value |= ($b & 0x7F) << $shift;
            $shift += 7;
        } while ($b & 0x80 && $shift < 35);

        return $value;
    }

    /** The MOTD is either a plain string or a chat component tree. */
    private function motd(mixed $description): ?string
    {
        if (is_string($description)) {
            return $this->clean($description);
        }

        if (is_array($description)) {
            $text = ($description['text'] ?? '');
            foreach ($description['extra'] ?? [] as $part) {
                $text .= is_array($part) ? ($part['text'] ?? '') : (string) $part;
            }

            return $this->clean($text);
        }

        return null;
    }

    private function clean(string $value): string
    {
        // Strip Minecraft § colour codes and control characters.
        $value = preg_replace('/\x{00A7}./u', '', $value) ?? $value;
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? $value;

        return mb_substr(trim($value), 0, 200);
    }
}
