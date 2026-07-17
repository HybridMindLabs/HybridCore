<?php

namespace Tests\Unit\Games;

use App\Games\Concurrent\A2SBatch;
use App\Games\Data\PlayerData;
use App\Games\Support\Buffer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The live server used for manual testing runs empty, so the player-list parser
 * never sees real names there. This exercises it against a hand-built
 * A2S_PLAYER payload in exactly the wire format Valve documents.
 */
class A2SBatchTest extends TestCase
{
    /** Reach the private parser directly — it's the byte-level part worth pinning. */
    private function parsePlayers(string $bodyAfterHeader): array
    {
        $method = new ReflectionMethod(A2SBatch::class, 'parsePlayers');
        $method->setAccessible(true);

        return $method->invoke(new A2SBatch, new Buffer($bodyAfterHeader));
    }

    private function player(int $index, string $name, int $score, float $duration): string
    {
        return chr($index).$name."\x00".pack('l', $score).pack('g', $duration);
    }

    public function test_it_parses_a_player_list(): void
    {
        $body = chr(2) // count
            .$this->player(0, 'Alice', 10, 65.0)
            .$this->player(1, 'Bob', 5, 12.5);

        $players = $this->parsePlayers($body);

        $this->assertCount(2, $players);
        $this->assertInstanceOf(PlayerData::class, $players[0]);
        $this->assertSame('Alice', $players[0]->name);
        $this->assertSame(10, $players[0]->score);
        $this->assertSame(65, $players[0]->duration);
        $this->assertSame('Bob', $players[1]->name);
    }

    public function test_it_skips_unnamed_slots(): void
    {
        // Some servers include an empty-name entry for the "server" itself.
        $body = chr(2)
            .$this->player(0, '', 0, 0.0)
            .$this->player(1, 'Real', 3, 1.0);

        $players = $this->parsePlayers($body);

        $this->assertCount(1, $players);
        $this->assertSame('Real', $players[0]->name);
    }

    public function test_a_count_larger_than_the_data_does_not_over_read(): void
    {
        // Claims 50 players but only ships one — must stop, not throw or hang.
        $body = chr(50).$this->player(0, 'Only', 1, 1.0);

        $players = $this->parsePlayers($body);

        $this->assertCount(1, $players);
    }

    public function test_control_characters_are_stripped_from_names(): void
    {
        $body = chr(1).$this->player(0, "Ha\x03xor\x01", 1, 1.0);

        $players = $this->parsePlayers($body);

        $this->assertSame('Haxor', $players[0]->name);
    }
}
