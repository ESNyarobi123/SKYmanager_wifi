<?php

namespace Database\Factories;

use App\Models\WifiUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WifiUser>
 */
class WifiUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mac_address' => strtoupper(implode(':', str_split(substr(md5(uniqid()), 0, 12), 2))),
            'phone_number' => '07'.fake()->numerify('########'),
            'is_active' => fake()->boolean(30),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => true]);
    }
}
