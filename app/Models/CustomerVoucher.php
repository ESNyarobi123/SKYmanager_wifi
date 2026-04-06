<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\CustomerVoucherFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CustomerVoucher extends Model
{
    /** @use HasFactory<CustomerVoucherFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'customer_id',
        'customer_billing_plan_id',
        'code',
        'batch_name',
        'status',
        'used_by_mac',
        'used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CustomerBillingPlan::class, 'customer_billing_plan_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @return Collection<int, static>
     */
    public static function generateBatch(
        string $customerId,
        string $customerBillingPlanId,
        int $count,
        string $batchName,
        ?string $prefix = null,
        ?Carbon $expiresAt = null
    ): Collection {
        $plan = CustomerBillingPlan::where('id', $customerBillingPlanId)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $vouchers = collect();

        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(($prefix ? $prefix.'-' : 'CV-').Str::random(8));

            while (static::where('code', $code)->exists()) {
                $code = strtoupper(($prefix ? $prefix.'-' : 'CV-').Str::random(8));
            }

            $vouchers->push(static::create([
                'customer_id' => $customerId,
                'customer_billing_plan_id' => $plan->id,
                'code' => $code,
                'batch_name' => $batchName,
                'status' => 'unused',
                'expires_at' => $expiresAt,
            ]));
        }

        return $vouchers;
    }

    /**
     * Redeem a customer voucher for a router owned by the same customer as the plan.
     *
     * @throws ModelNotFoundException
     * @throws \InvalidArgumentException
     */
    public static function redeemForRouter(string $code, string $mac, Router $router): static
    {
        $voucher = static::where('code', strtoupper(trim($code)))->firstOrFail();

        if ($voucher->customer_id !== $router->user_id) {
            throw new \InvalidArgumentException('This voucher is not valid for this hotspot.');
        }

        if ($voucher->status === 'used') {
            throw new \InvalidArgumentException('This voucher has already been used.');
        }

        if ($voucher->status === 'expired' || $voucher->isExpired()) {
            throw new \InvalidArgumentException('This voucher has expired.');
        }

        $voucher->update([
            'status' => 'used',
            'used_by_mac' => strtoupper($mac),
            'used_at' => now(),
        ]);

        return $voucher->load('plan');
    }
}
