<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            [
                'name' => 'Counter-Strike 1.6',
                'slug' => 'cs16',
                'icon' => 'crosshair',
                'color' => '#f97316',
                'query_driver' => 'cs16',
                'default_port' => 27015,
                'sort_order' => 1,
            ],
            [
                'name' => 'Counter-Strike 2',
                'slug' => 'cs2',
                'icon' => 'crosshair',
                'color' => '#eab308',
                'query_driver' => 'source',
                'default_port' => 27015,
                'sort_order' => 2,
            ],
            [
                'name' => 'Minecraft',
                'slug' => 'minecraft',
                'icon' => 'box',
                'color' => '#22c55e',
                'query_driver' => 'minecraft_java',
                'default_port' => 25565,
                'sort_order' => 3,
            ],
            [
                'name' => "Garry's Mod",
                'slug' => 'gmod',
                'icon' => 'wrench',
                'color' => '#8b5cf6',
                'query_driver' => 'gmod',
                'default_port' => 27015,
                'sort_order' => 4,
            ],
            [
                'name' => 'FiveM',
                'slug' => 'fivem',
                'icon' => 'car',
                'color' => '#06b6d4',
                'query_driver' => 'fivem',
                'default_port' => 30120,
                'sort_order' => 5,
            ],
            [
                'name' => 'Rust',
                'slug' => 'rust',
                'icon' => 'flame',
                'color' => '#ef4444',
                'query_driver' => 'rust',
                'default_port' => 28015,
                'default_query_port' => 28017, // game port + 2
                'sort_order' => 6,
            ],
            [
                'name' => 'ARK: Survival Evolved',
                'slug' => 'ark',
                'icon' => 'shield',
                'color' => '#84cc16',
                'query_driver' => 'arkse',
                'default_port' => 7777,
                'default_query_port' => 27015, // ARK's query port is unrelated to the game port
                'sort_order' => 7,
            ],
            [
                'name' => '7 Days to Die',
                'slug' => '7dtd',
                'icon' => 'skull',
                'color' => '#dc2626',
                'query_driver' => 'sevendaystodie',
                'default_port' => 26900,
                'default_query_port' => 26901, // game port + 1
                'sort_order' => 8,
            ],
            [
                'name' => 'Team Fortress 2',
                'slug' => 'tf2',
                'icon' => 'hat',
                'color' => '#b45309',
                'query_driver' => 'tf2',
                'default_port' => 27015,
                'sort_order' => 9,
            ],
            [
                'name' => 'Unturned',
                'slug' => 'unturned',
                'icon' => 'ghost',
                'color' => '#64748b',
                'query_driver' => 'unturned',
                'default_port' => 27015,
                'default_query_port' => 27016, // game port + 1
                'sort_order' => 10,
            ],

            // Source-engine games the drivers already handle — just not seeded before.
            [
                'name' => 'Counter-Strike: Source',
                'slug' => 'css',
                'icon' => 'crosshair',
                'color' => '#f59e0b',
                'query_driver' => 'css',
                'default_port' => 27015,
                'sort_order' => 11,
            ],
            [
                'name' => 'Counter-Strike: GO (Legacy)',
                'slug' => 'csgo',
                'icon' => 'crosshair',
                'color' => '#ca8a04',
                'query_driver' => 'csgo',
                'default_port' => 27015,
                'sort_order' => 12,
            ],
            [
                'name' => 'Left 4 Dead 2',
                'slug' => 'l4d2',
                'icon' => 'biohazard',
                'color' => '#16a34a',
                'query_driver' => 'l4d2',
                'default_port' => 27015,
                'sort_order' => 13,
            ],
            [
                'name' => 'Valheim',
                'slug' => 'valheim',
                'icon' => 'axe',
                'color' => '#0ea5e9',
                'query_driver' => 'valheim',
                'default_port' => 2456,
                'default_query_port' => 2457, // game port + 1
                'sort_order' => 14,
            ],
            [
                'name' => 'Squad',
                'slug' => 'squad',
                'icon' => 'users',
                'color' => '#65a30d',
                'query_driver' => 'squad',
                'default_port' => 7787,
                'default_query_port' => 27165,
                'sort_order' => 15,
            ],
            [
                'name' => 'Insurgency',
                'slug' => 'insurgency',
                'icon' => 'target',
                'color' => '#b91c1c',
                'query_driver' => 'insurgency',
                'default_port' => 27015,
                'sort_order' => 16,
            ],
            [
                'name' => 'RedM',
                'slug' => 'redm',
                'icon' => 'car',
                'color' => '#dc2626',
                'query_driver' => 'redm',
                'default_port' => 30120,
                'sort_order' => 17,
            ],
        ];

        foreach ($games as $game) {
            Game::updateOrCreate(['slug' => $game['slug']], $game);
        }

        // Drop games we no longer ship (Hytale — unreleased, protocol unknown),
        // but only when nobody has added a server for them.
        Game::whereIn('slug', ['hytale'])
            ->whereDoesntHave('servers')
            ->delete();
    }
}
