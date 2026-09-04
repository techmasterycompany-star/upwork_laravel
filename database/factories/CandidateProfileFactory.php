<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CandidateProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'candidate']),
            'bio' => $this->faker->paragraph(),
            'portfolio_url' => $this->faker->url(),
            'resume' => 'resumes/'.$this->faker->uuid().'.pdf',
            'phone' => $this->faker->phoneNumber(),
            'location' => $this->faker->city(),
        ];
    }
}
