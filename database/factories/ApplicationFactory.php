<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\JobListing;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_id' => JobListing::factory(),
            'candidate_id' => CandidateProfile::factory(),
            'resume' => 'resumes/'.$this->faker->uuid().'.pdf',
            'cover_letter' => $this->faker->paragraph(),
            'contact_email' => $this->faker->safeEmail(),
            'contact_phone' => $this->faker->phoneNumber(),
            'status' => 'submitted',
            'rejection_reason' => null,
            'reviewed_at' => null,
        ];
    }

    public function underReview(): static
    {
        return $this->state(fn () => [
            'status' => 'under_review',
            'reviewed_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => 'accepted',
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'reviewed_at' => now(),
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }
}
