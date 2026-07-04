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
                'sort_order' => 6,
            ],
            [
                'name' => 'ARK: Survival Evolved',
                'slug' => 'ark',
                'icon' => 'shield',
                'color' => '#84cc16',
                'query_driver' => 'arkse',
                'default_port' => 27015,
                'sort_order' => 7,
            ],
            [
                'name' => '7 Days to Die',
                'slug' => '7dtd',
                'icon' => 'skull',
                'color' => '#dc2626',
                'query_driver' => 'sevendaystodie',
                'default_port' => 26900,
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
                'sort_order' => 10,
            ],
            [
                'name' => 'Hytale',
                'slug' => 'hytale',
                'icon' => 'sparkles',
                'color' => '#a855f7',
                'query_driver' => 'minecraft_java',
                'default_port' => 25565,
                'sort_order' => 11,
                'is_active' => false,
            ],
        ];

        foreach ($games as $game) {
            Game::updateOrCreate(['slug' => $game['slug']], $game);
        }
    }
}
