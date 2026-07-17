<?php

namespace Tests\Feature\Games;

use App\Games\GameDriverRegistry;
use App\Models\Game;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the shipped game list against the drivers: a game the seeder adds with
 * no driver to answer it would show as permanently offline on a fresh install.
 */
class GameSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GameSeeder::class);
    }

    public function test_every_seeded_game_has_a_driver(): void
    {
        $registry = app(GameDriverRegistry::class);

        foreach (Game::all() as $game) {
            $this->assertTrue(
                $registry->supports($game->query_driver),
                "{$game->name} uses driver '{$game->query_driver}' with nothing to handle it.",
            );
        }
    }

    public function test_games_with_a_separate_query_port_have_it_set(): void
    {
        // These are exactly the games where the query port differs from the game
        // port — the reason they used to show offline until an admin knew the
        // offset. Now the default is shipped.
        $expected = [
            'rust' => 28017,
            'ark' => 27015,
            '7dtd' => 26901,
            'unturned' => 27016,
            'valheim' => 2457,
        ];

        foreach ($expected as $slug => $queryPort) {
            $this->assertSame(
                $queryPort,
                Game::where('slug', $slug)->value('default_query_port'),
                "{$slug} should ship a default query port.",
            );
        }
    }

    public function test_source_games_share_the_game_port(): void
    {
        // For Source-engine games the two ports are the same, so no override.
        foreach (['cs16', 'cs2', 'gmod', 'tf2', 'css'] as $slug) {
            $this->assertNull(
                Game::where('slug', $slug)->value('default_query_port'),
                "{$slug} should not override the query port.",
            );
        }
    }

    public function test_unreleased_games_are_not_shipped(): void
    {
        // Hytale has no released protocol; it must not sit in the list resolving
        // to a Minecraft driver that would never work against it.
        $this->assertDatabaseMissing('games', ['slug' => 'hytale']);
    }
}
