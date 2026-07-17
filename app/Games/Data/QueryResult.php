<?php

namespace App\Games\Data;

/**
 * The outcome of querying one server. Every driver returns exactly this, so
 * ServerQueryService never has to know which game it asked about.
 *
 * Offline results carry a human-readable reason, so a server that won't answer
 * can say why in the admin panel instead of a silent "offline".
 */
final readonly class QueryResult
{
    /**
     * @param  list<PlayerData>  $players
     */
    public function __construct(
        public bool $online,
        public ?string $name = null,
        public ?string $map = null,
        public int $playersOnline = 0,
        public int $playersMax = 0,
        public ?int $ping = null,
        public bool $passwordProtected = false,
        public bool $secure = false,
        public ?string $version = null,
        public array $players = [],
        public ?string $failureReason = null,
    ) {}

    /** A failed query, with the reason the operator will see. */
    public static function offline(string $reason): self
    {
        return new self(online: false, failureReason: $reason);
    }
}
