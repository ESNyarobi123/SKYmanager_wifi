<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'amount' => fake()->randomElement([500, 1000, 1500, 2000, 3000, 5000]),
            'provider' => fake()->randomElement(['M-PESA', 'TIGO-PESA', 'AIRTEL-MONEY']),
            'reference' => 'SKY-'.strtoupper(fake()->bothify('??######')),
            'transaction_id' => fake()->optional(0.8)->uuid(),
            'status' => fake()->randomElement(['pending', 'success', 'failed']),
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'success']);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'pending']);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'failed']);
    }
}
