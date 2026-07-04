<?php

namespace Database\Factories;

use App\Models\IpBan;
use Illuminate\Database\Eloquent\Factories\Factory;

class IpBanFactory extends Factory
{
    protected $model = IpBan::class;

    public function definition(): array
    {
        return [
            'ip' => $this->faker->ipv4(),
            'reason' => $this->faker->sentence(),
            'banned_by' => null,
            'expires_at' => null,
        ];
    }
}
