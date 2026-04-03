<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotPayment extends Model
{
    use HasUlids;

    protected $fillable = [
        'router_id',
        'plan_id',
        'client_mac',
        'client_ip',
        'phone',
        'amount',
        'reference',
        'transaction_id',
        'status',
        'authorized_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'authorized_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CustomerBillingPlan::class, 'plan_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAuthorized(): bool
    {
        return $this->status === 'authorized';
    }
}
