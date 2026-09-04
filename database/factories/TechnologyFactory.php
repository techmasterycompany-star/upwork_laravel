<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Technology>
 */
class TechnologyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word() . '-' . fake()->unique()->numberBetween(1000, 999999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
