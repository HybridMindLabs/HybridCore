<?php

namespace App\Games\Contracts;

use App\Games\Data\QueryResult;

/**
 * A protocol for asking a game server about itself.
 *
 * To support a new game, drop a class implementing this into app/Games/Drivers.
 * The registry discovers it automatically from handles() — there is no list to
 * edit and nothing to register by hand.
 *
 * A driver must never throw: it catches its own transport and parse errors and
 * returns QueryResult::offline() with a reason. The caller treats any driver
 * the same way.
 */
interface GameDriver
{
    /**
     * The query_driver slugs this driver answers for, e.g. ['source', 'cs16'].
     * These match Game::query_driver in the database.
     *
     * @return list<string>
     */
    public static function handles(): array;

    public function query(string $host, int $port, float $timeout = 3.0): QueryResult;
}
