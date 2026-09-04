<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => 'candidate',
            'is_blocked' => false,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Employer role, with an EmployerProfile automatically created.
     */
    public function employer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'employer',
        ])->afterCreating(function (\App\Models\User $user) {
            if (! $user->employerProfile) {
                $profile = $user->employerProfile()->create([
                    'company_name' => $user->name . ' Inc.',
                    'free_jobs_used' => 0,
                ]);
                $user->setRelation('employerProfile', $profile);
            }
        });
    }

    /**
     * Candidate role, with a CandidateProfile automatically created.
     */
    public function candidate(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'candidate',
        ])->afterCreating(function (\App\Models\User $user) {
            if (! $user->candidateProfile) {
                $profile = $user->candidateProfile()->create([]);
                $user->setRelation('candidateProfile', $profile);
            }
        });
    }

    /**
     * Admin role. There is no public registration endpoint for admins
     * (by design), so tests create them directly via this factory state.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    /**
     * Blocked user account.
     */
    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_blocked' => true,
        ]);
    }
}