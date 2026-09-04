<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'application_status_changed',
            'title' => $this->faker->sentence(3),
            'content' => $this->faker->sentence(),
            'data' => null,
            'is_read' => false,
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => [
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
