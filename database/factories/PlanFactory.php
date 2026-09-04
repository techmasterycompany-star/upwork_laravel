<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true) . ' Plan',
            'job_post_limit' => fake()->randomElement([5, 10, 20, null]),
            'price_monthly' => fake()->randomFloat(2, 9, 199),
            'price_yearly' => fake()->randomFloat(2, 99, 1999),
        ];
    }
}
