<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'Password@123', // hashed by the model cast; development/test only
            'remember_token' => null,
            'role' => 'viewer',
            'is_active' => true,
        ];
    }
}
