<?php

namespace Database\Factories;

use App\Models\CustomerBillingPlan;
use App\Models\CustomerVoucher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerVoucher>
 */
class CustomerVoucherFactory extends Factory
{
    protected $model = CustomerVoucher::class;

    public function definition(): array
    {
        $user = User::factory()->create();
        $plan = CustomerBillingPlan::factory()->create(['customer_id' => $user->id]);

        return [
            'customer_id' => $user->id,
            'customer_billing_plan_id' => $plan->id,
            'code' => 'CV-'.strtoupper(Str::random(8)),
            'batch_name' => 'Factory batch',
            'status' => 'unused',
        ];
    }
}
