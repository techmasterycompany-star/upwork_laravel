<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\EmployerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JobListing>
 */
class JobListingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employer_id' => EmployerProfile::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->jobTitle(),
            'description' => fake()->paragraph(),
            'responsibilities' => fake()->paragraph(),
            'requirements' => fake()->paragraph(),
            'location' => fake()->city(),
            'work_type' => fake()->randomElement(['remote', 'onsite', 'hybrid']),
            'salary_min' => fake()->numberBetween(30000, 60000),
            'salary_max' => fake()->numberBetween(60000, 120000),
            'experience_level' => fake()->randomElement(['entry', 'mid', 'senior']),
            'application_deadline' => now()->addDays(30)->toDateString(),
            'status' => 'approved',
            'rejection_reason' => null,
            'views_count' => 0,
            'applications_count' => 0,
        ];
    }
}
