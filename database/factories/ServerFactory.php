<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Server;
use App\Support\HostSafety;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'ip' => $this->publicIp(),
            'port' => $this->faker->numberBetween(1024, 65535),
            'name' => $this->faker->words(3, true).' Server',
            'is_active' => true,
        ];
    }

    /**
     * faker->ipv4() is unconstrained and occasionally rolls a private/reserved
     * address (10.x, 192.168.x, ...) — reusing the real safety check here
     * instead of re-deriving "which ranges count as public" keeps this in
     * sync with HostSafety by construction, not by two lists staying equal.
     */
    private function publicIp(): string
    {
        do {
            $ip = $this->faker->ipv4();
        } while (! HostSafety::isSafePublicHost($ip));

        return $ip;
    }
}
