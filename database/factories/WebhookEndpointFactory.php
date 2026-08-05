<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\Hooks;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<WebhookEndpoint> */
class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->unique()->words(2, true)),
            'url' => 'https://example.com/webhooks/'.Str::random(8),
            'secret' => Str::random(40),
            'events' => [Hooks::USER_REGISTERED],
            'is_active' => true,
            'created_by' => User::factory(['is_admin' => true]),
        ];
    }
}
