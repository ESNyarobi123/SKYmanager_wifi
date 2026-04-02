<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $seq = 0;
        $seq++;
        $subtotal = $this->faker->randomFloat(2, 5000, 50000);

        return [
            'invoice_number' => 'INV-'.now()->year.'-'.str_pad($seq, 6, '0', STR_PAD_LEFT),
            'customer_id' => Customer::factory(),
            'payment_id' => null,
            'subscription_id' => null,
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'total' => $subtotal,
            'currency' => 'TZS',
            'status' => $this->faker->randomElement(['issued', 'paid']),
            'notes' => null,
            'issued_at' => now(),
            'due_at' => now()->addDays(7),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => 'paid']);
    }

    public function issued(): static
    {
        return $this->state(fn () => ['status' => 'issued']);
    }
}
