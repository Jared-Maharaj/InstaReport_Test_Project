<?php

namespace Database\Factories;

use App\Models\MagicLinkToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MagicLinkToken>
 */
class MagicLinkTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', bin2hex(random_bytes(32))),
            'expires_at' => now()->addMinutes(15),
            'consumed_at' => null,
        ];
    }
}