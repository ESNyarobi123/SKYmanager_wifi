<?php

namespace Database\Factories;

use App\Models\BillingPlan;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => BillingPlan::factory(),
            'code' => strtoupper(fake()->unique()->bothify('??##??##')),
            'batch_name' => fake()->optional(0.7)->word(),
            'status' => 'unused',
            'used_by_mac' => null,
            'used_at' => null,
            'expires_at' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'used',
            'used_by_mac' => fake()->macAddress(),
            'used_at' => now()->subHours(fake()->numberBetween(1, 72)),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }
}
