<?php

namespace App\Games\Data;

/**
 * One player on a server. Immutable — a driver produces these and nothing
 * mutates them afterwards.
 */
final readonly class PlayerData
{
    public function __construct(
        public string $name,
        public int $score = 0,
        public int $duration = 0, // seconds connected
    ) {}
}
