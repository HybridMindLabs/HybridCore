<?php

namespace App\Games\Support;

use RuntimeException;

/**
 * A cursor over a binary string that refuses to read past its end.
 *
 * Every read is bounds-checked, so a malformed or hostile response — a modded
 * server sending garbage, a truncated UDP packet, a length field claiming more
 * bytes than arrived — throws a clean exception instead of reading uninitialised
 * memory or looping forever. This is the security boundary for everything the
 * drivers parse.
 */
final class Buffer
{
    private int $position = 0;

    private readonly int $length;

    public function __construct(private string $data)
    {
        $this->length = strlen($data);
    }

    public function remaining(): int
    {
        return $this->length - $this->position;
    }

    public function isEmpty(): bool
    {
        return $this->remaining() <= 0;
    }

    /** Raw bytes without advancing — used to sniff a response type. */
    public function peek(int $length = 1): string
    {
        if ($this->position + $length > $this->length) {
            return '';
        }

        return substr($this->data, $this->position, $length);
    }

    public function get(int $length): string
    {
        if ($length < 0 || $this->position + $length > $this->length) {
            throw new RuntimeException("Buffer overrun: wanted {$length} bytes, {$this->remaining()} left");
        }

        $out = substr($this->data, $this->position, $length);
        $this->position += $length;

        return $out;
    }

    /** Everything from the cursor to the end. */
    public function getRest(): string
    {
        return $this->get($this->remaining());
    }

    public function readUInt8(): int
    {
        return ord($this->get(1));
    }

    public function readInt16(): int
    {
        $v = unpack('vshort', $this->get(2));

        // unsigned 16-bit -> signed
        return $v['short'] >= 0x8000 ? $v['short'] - 0x10000 : $v['short'];
    }

    public function readUInt16(): int
    {
        return unpack('vshort', $this->get(2))['short'];
    }

    public function readInt32(): int
    {
        return unpack('lint', $this->get(4))['int'];
    }

    public function readFloat32(): float
    {
        return unpack('gfloat', $this->get(4))['float'];
    }

    public function readUInt64(): string
    {
        // Returned as a string: SteamIDs exceed PHP's int on 32-bit and are
        // only ever displayed, never used in arithmetic here.
        return (string) unpack('Pv', $this->get(8))['v'];
    }

    /**
     * A NUL-terminated string. Capped so a response missing its terminator
     * can't be walked to the end of a large buffer.
     */
    public function readString(int $max = 1024): string
    {
        $out = '';

        while ($this->position < $this->length) {
            $char = $this->data[$this->position++];

            if ($char === "\x00") {
                return $out;
            }

            $out .= $char;

            if (strlen($out) >= $max) {
                break;
            }
        }

        return $out;
    }
}
