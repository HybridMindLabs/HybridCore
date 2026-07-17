<?php

namespace Tests\Unit\Games;

use App\Games\Support\Buffer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The Buffer is the security boundary: every byte the drivers parse comes from
 * an untrusted game server, so a read past the end must fail cleanly rather than
 * expose memory or loop.
 */
class BufferTest extends TestCase
{
    public function test_it_reads_the_integer_types_it_advertises(): void
    {
        // uint8=1, int16=-2 (LE), int32=1000, float32=1.5
        $b = new Buffer("\x01".pack('v', 0xFFFE).pack('l', 1000).pack('g', 1.5));

        $this->assertSame(1, $b->readUInt8());
        $this->assertSame(-2, $b->readInt16());
        $this->assertSame(1000, $b->readInt32());
        $this->assertSame(1.5, $b->readFloat32());
        $this->assertTrue($b->isEmpty());
    }

    public function test_read_string_stops_at_the_null_terminator(): void
    {
        $b = new Buffer("hello\x00world\x00");

        $this->assertSame('hello', $b->readString());
        $this->assertSame('world', $b->readString());
    }

    public function test_reading_past_the_end_throws_rather_than_over_reading(): void
    {
        $b = new Buffer("\x01\x02");

        $b->readUInt8();
        $b->readUInt8();

        $this->expectException(RuntimeException::class);
        $b->readInt32(); // only 0 bytes left, wants 4
    }

    public function test_an_unterminated_string_is_capped_not_endless(): void
    {
        // No NUL byte at all — must stop at the cap, not walk off the end.
        $b = new Buffer(str_repeat('A', 5000));

        $this->assertSame(1024, strlen($b->readString()));
    }

    public function test_peek_does_not_advance(): void
    {
        $b = new Buffer("\x41\x42");

        $this->assertSame("\x41", $b->peek());
        $this->assertSame("\x41", $b->peek());   // still there
        $this->assertSame(0x41, $b->readUInt8()); // now consumed
    }

    public function test_peek_past_the_end_returns_empty_not_an_error(): void
    {
        $b = new Buffer('');

        $this->assertSame('', $b->peek());
    }
}
