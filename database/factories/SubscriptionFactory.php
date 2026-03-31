<?php

namespace Database\Factories;

use App\Models\BillingPlan;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\WifiUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plan = BillingPlan::inRandomOrder()->first() ?? BillingPlan::factory()->create();

        return [
            'wifi_user_id' => WifiUser::factory(),
            'plan_id' => $plan->id,
            'router_id' => Router::factory(),
            'expires_at' => fake()->dateTimeBetween('-2 days', '+2 days'),
            'status' => fake()->randomElement(['active', 'expired']),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expires_at' => now()->addHours(fake()->numberBetween(1, 24)),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subHours(fake()->numberBetween(1, 48)),
        ]);
    }
}
