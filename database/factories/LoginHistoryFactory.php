<?php

namespace Database\Factories;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoginHistoryFactory extends Factory
{
    protected $model = LoginHistory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'created_at' => now(),
        ];
    }
}
