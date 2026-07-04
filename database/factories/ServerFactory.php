<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'ip' => $this->faker->ipv4(),
            'port' => $this->faker->numberBetween(1024, 65535),
            'name' => $this->faker->words(3, true).' Server',
            'is_active' => true,
        ];
    }
}
