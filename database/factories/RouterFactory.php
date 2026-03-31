<?php

namespace Database\Factories;

use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Router>
 */
class RouterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city().' Router',
            'ip_address' => fake()->localIpv4(),
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => fake()->password(8, 16),
            'is_online' => fake()->boolean(70),
            'last_seen' => fake()->optional(0.8)->dateTimeBetween('-1 hour', 'now'),
        ];
    }

    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online' => true,
            'last_seen' => now(),
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online' => false,
        ]);
    }
}
