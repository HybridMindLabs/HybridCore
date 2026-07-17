<?php

namespace App\Games\Support;

use RuntimeException;

/**
 * A single UDP conversation with a game server: send a payload, read the reply.
 *
 * Everything is bounded — a connect timeout, a read timeout, and a hard cap on
 * how many bytes we will accept — so a slow or hostile server can neither block
 * the queue worker nor flood us. One socket per instance; always closed.
 */
final class UdpSocket
{
    /** Never accept more than this from one server (guards against amplification). */
    private const MAX_RESPONSE = 65_535;

    /** @var resource|null */
    private $handle = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly float $timeout = 3.0,
    ) {}

    private function connect(): void
    {
        if ($this->handle !== null) {
            return;
        }

        $handle = @fsockopen("udp://{$this->host}", $this->port, $errno, $errstr, $this->timeout);

        if ($handle === false) {
            throw new RuntimeException("Cannot reach {$this->host}:{$this->port} — {$errstr}");
        }

        stream_set_timeout($handle, (int) $this->timeout, (int) (($this->timeout - (int) $this->timeout) * 1_000_000));
        $this->handle = $handle;
    }

    public function send(string $payload): void
    {
        $this->connect();
        fwrite($this->handle, $payload);
    }

    /** Send, then read one datagram. Throws on timeout or empty reply. */
    public function exchange(string $payload): string
    {
        $this->send($payload);

        return $this->receive();
    }

    public function receive(): string
    {
        $this->connect();

        $data = fread($this->handle, self::MAX_RESPONSE);

        if ($data === false || $data === '') {
            $meta = stream_get_meta_data($this->handle);
            throw new RuntimeException($meta['timed_out'] ? 'No response (timed out)' : 'Empty response');
        }

        return $data;
    }

    public function close(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
