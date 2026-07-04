<?php

namespace Database\Factories;

use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailLogFactory extends Factory
{
    protected $model = EmailLog::class;

    public function definition(): array
    {
        return [
            'to' => $this->faker->safeEmail(),
            'subject' => $this->faker->sentence(4),
            'template_slug' => $this->faker->randomElement(['welcome', 'test', 'digest', null]),
            'status' => $this->faker->randomElement(['sent', 'failed', 'skipped']),
            'error' => null,
            'user_id' => null,
            'sent_at' => now(),
        ];
    }
}
