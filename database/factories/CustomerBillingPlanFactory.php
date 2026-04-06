<?php

namespace Database\Factories;

use App\Models\CustomerBillingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerBillingPlan>
 */
class CustomerBillingPlanFactory extends Factory
{
    protected $model = CustomerBillingPlan::class;

    public function definition(): array
    {
        return [
            'customer_id' => User::factory()->customer(),
            'name' => fake()->words(2, true).' Plan',
            'price' => fake()->numberBetween(500, 5000),
            'duration_minutes' => 60,
            'data_quota_mb' => null,
            'upload_speed_kbps' => null,
            'download_speed_kbps' => null,
            'description' => null,
            'is_active' => true,
        ];
    }
}
