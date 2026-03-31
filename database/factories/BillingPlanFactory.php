<?php

namespace Database\Factories;

use App\Models\BillingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingPlan>
 */
class BillingPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $duration = fake()->randomElement([30, 60, 120, 180, 360, 720, 1440]);

        return [
            'name' => fake()->randomElement(['Basic', 'Standard', 'Premium', 'VIP', 'Student', 'Night']).' '.($duration >= 60 ? ($duration / 60).'h' : $duration.'m'),
            'price' => fake()->randomElement([500, 1000, 1500, 2000, 3000, 5000]),
            'duration_minutes' => $duration,
            'upload_limit' => fake()->randomElement([1, 2, 5, 10]),
            'download_limit' => fake()->randomElement([2, 5, 10, 20]),
            'description' => fake()->optional(0.6)->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
