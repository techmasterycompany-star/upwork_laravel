<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployerProfile>
 */
class EmployerProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'employer']),
            'company_name' => fake()->company(),
            'description' => fake()->sentence(),
            'industry' => fake()->word(),
            'website' => fake()->url(),
            'company_logo' => null,
            'free_jobs_used' => 0,
        ];
    }
}
