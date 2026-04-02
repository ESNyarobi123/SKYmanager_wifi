<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Voucher extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'plan_id',
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

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'plan_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Generate a batch of unique voucher codes for a given plan.
     *
     * @return Collection<int, static>
     */
    public static function generateBatch(string $planId, int $count, string $batchName, ?string $prefix = null, ?Carbon $expiresAt = null): Collection
    {
        $vouchers = collect();

        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(($prefix ? $prefix.'-' : '').Str::random(8));

            while (static::where('code', $code)->exists()) {
                $code = strtoupper(($prefix ? $prefix.'-' : '').Str::random(8));
            }

            $vouchers->push(static::create([
                'plan_id' => $planId,
                'code' => $code,
                'batch_name' => $batchName,
                'status' => 'unused',
                'expires_at' => $expiresAt,
            ]));
        }

        return $vouchers;
    }

    /**
     * Redeem a voucher for a given MAC address.
     *
     * @throws \Exception
     */
    public static function redeem(string $code, string $mac): static
    {
        $voucher = static::where('code', strtoupper(trim($code)))->firstOrFail();

        if ($voucher->status === 'used') {
            throw new \Exception('This voucher has already been used.');
        }

        if ($voucher->status === 'expired' || $voucher->isExpired()) {
            throw new \Exception('This voucher has expired.');
        }

        $voucher->update([
            'status' => 'used',
            'used_by_mac' => strtoupper($mac),
            'used_at' => now(),
        ]);

        return $voucher->load('plan');
    }
}
