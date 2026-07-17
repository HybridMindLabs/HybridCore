<?php

namespace Tests\Feature\Games;

use App\Games\Contracts\GameDriver;
use App\Games\Data\QueryResult;
use App\Games\Drivers\FiveMDriver;
use App\Games\Drivers\MinecraftDriver;
use App\Games\Drivers\SourceDriver;
use App\Games\GameDriverRegistry;
use Tests\TestCase;

/**
 * A new game should be a new class in app/Games/Drivers and nothing else. These
 * pin that contract: the registry discovers drivers from the folder, maps every
 * slug they declare, and lets an extension add its own.
 */
class GameDriverRegistryTest extends TestCase
{
    private function registry(): GameDriverRegistry
    {
        return new GameDriverRegistry;
    }

    public function test_it_discovers_the_core_drivers_from_the_folder(): void
    {
        $registry = $this->registry();

        $this->assertInstanceOf(SourceDriver::class, $registry->driverFor('cs2'));
        $this->assertInstanceOf(SourceDriver::class, $registry->driverFor('cs16'));
        $this->assertInstanceOf(SourceDriver::class, $registry->driverFor('rust'));
        $this->assertInstanceOf(MinecraftDriver::class, $registry->driverFor('minecraft_java'));
        $this->assertInstanceOf(FiveMDriver::class, $registry->driverFor('fivem'));
    }

    public function test_every_slug_the_seeder_uses_has_a_driver(): void
    {
        // The games the default seeder ships must all resolve, or a fresh
        // install would show them permanently offline.
        $seeded = ['cs16', 'source', 'minecraft_java', 'gmod', 'fivem', 'rust', 'arkse', 'sevendaystodie', 'tf2', 'unturned'];

        $registry = $this->registry();

        foreach ($seeded as $slug) {
            $this->assertTrue($registry->supports($slug), "No driver handles the seeded slug '{$slug}'.");
        }
    }

    public function test_an_unknown_slug_resolves_to_null(): void
    {
        $this->assertNull($this->registry()->driverFor('no-such-game'));
        $this->assertFalse($this->registry()->supports('no-such-game'));
    }

    public function test_an_extension_can_register_its_own_driver(): void
    {
        $registry = $this->registry();
        $this->assertFalse($registry->supports('mygame'));

        $registry->extend(FakeExtensionDriver::class);

        $this->assertTrue($registry->supports('mygame'));
        $this->assertInstanceOf(FakeExtensionDriver::class, $registry->driverFor('mygame'));
    }
}

/** Stand-in for a driver an extension would ship. */
final class FakeExtensionDriver implements GameDriver
{
    public static function handles(): array
    {
        return ['mygame'];
    }

    public function query(string $host, int $port, float $timeout = 3.0): QueryResult
    {
        return QueryResult::offline('stub');
    }
}
