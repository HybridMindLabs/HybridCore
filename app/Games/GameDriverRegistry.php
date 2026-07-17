<?php

namespace App\Games;

use App\Games\Contracts\GameDriver;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Maps a game's query_driver slug to the driver that answers it.
 *
 * Core drivers are discovered by scanning app/Games/Drivers — a new game is a
 * new class in that folder and nothing else. Extensions can add their own by
 * calling extend(), so a game the core doesn't cover can ship in an extension.
 */
class GameDriverRegistry
{
    /** @var array<string, class-string<GameDriver>>|null */
    private ?array $map = null;

    /** @var array<string, class-string<GameDriver>> Explicit registrations (extensions). */
    private array $extra = [];

    /**
     * Register a driver by hand — for extensions adding a game the core has no
     * driver for. Its handles() slugs win over a core driver with the same slug.
     *
     * @param  class-string<GameDriver>  $driver
     */
    public function extend(string $driver): void
    {
        foreach ($driver::handles() as $slug) {
            $this->extra[$slug] = $driver;
        }

        $this->map = null; // rebuild on next lookup
    }

    public function driverFor(string $slug): ?GameDriver
    {
        $class = $this->map()[$slug] ?? null;

        return $class ? app($class) : null;
    }

    public function supports(string $slug): bool
    {
        return isset($this->map()[$slug]);
    }

    /** @return list<string> Every slug any driver handles. */
    public function slugs(): array
    {
        return array_keys($this->map());
    }

    /** @return array<string, class-string<GameDriver>> */
    private function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $map = [];

        foreach ($this->discover() as $class) {
            foreach ($class::handles() as $slug) {
                $map[$slug] = $class;
            }
        }

        // Extension registrations layer on top and can override core slugs.
        return $this->map = [...$map, ...$this->extra];
    }

    /**
     * Every GameDriver class under app/Games/Drivers.
     *
     * @return list<class-string<GameDriver>>
     */
    private function discover(): array
    {
        $dir = app_path('Games/Drivers');

        if (! is_dir($dir)) {
            return [];
        }

        $drivers = [];

        foreach (Finder::create()->files()->in($dir)->name('*.php') as $file) {
            /** @var SplFileInfo $file */
            $class = 'App\\Games\\Drivers\\'.Str::of($file->getRelativePathname())
                ->replace(['/', '.php'], ['\\', ''])
                ->toString();

            if (is_subclass_of($class, GameDriver::class)) {
                $drivers[] = $class;
            }
        }

        return $drivers;
    }
}
