<?php

namespace Database\Factories;

use App\Models\Theme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Theme>
 */
class ThemeFactory extends Factory
{
    protected $model = Theme::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $slug = Str::slug($name);

        return [
            'name' => ucwords($name),
            'slug' => $slug,
            'version' => fake()->semver(),
            'author' => fake()->name(),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(['official', 'community', 'custom']),
            'path' => 'Test/'.Str::studly($slug),
            'active' => false,
            'preview_image' => null,
            'installed_at' => now(),
            'activated_at' => null,
            'metadata' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['active' => true, 'activated_at' => now()]);
    }
}
