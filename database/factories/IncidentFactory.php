<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'reference' => Incident::generateReference(),
            'reporter_id' => User::factory(),
            'type' => 'SECURITY',
            'category' => 'THEFT',
            'description' => fake()->paragraph(),
            'severity' => 'LOW',
            'status' => 'OPEN',
            'address' => fake()->streetAddress(),
            'latitude' => null,
            'longitude' => null,
            'occurred_at' => now()->subHour(),
        ];
    }
}