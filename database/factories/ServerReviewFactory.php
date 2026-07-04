<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\ServerReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServerReviewFactory extends Factory
{
    protected $model = ServerReview::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'server_id' => Server::factory(),
            'rating' => $this->faker->numberBetween(1, 5),
            'body' => $this->faker->sentence(),
        ];
    }
}
