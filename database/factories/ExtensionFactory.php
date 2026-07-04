<?php

namespace Database\Factories;

use App\Models\Extension;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Extension>
 */
class ExtensionFactory extends Factory
{
    protected $model = Extension::class;

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
            'enabled' => false,
            'installed_at' => now(),
            'enabled_at' => null,
            'disabled_at' => null,
            'metadata' => null,
        ];
    }
}
