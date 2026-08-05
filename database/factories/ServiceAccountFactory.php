<?php

namespace Database\Factories;

use App\Models\ServiceAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceAccount> */
class ServiceAccountFactory extends Factory
{
    protected $model = ServiceAccount::class;

    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->unique()->words(2, true)),
            'created_by' => User::factory(['is_admin' => true]),
        ];
    }
}
