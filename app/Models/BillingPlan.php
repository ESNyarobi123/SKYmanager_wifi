<?php

namespace App\Models;

use Database\Factories\BillingPlanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPlan extends Model
{
    /** @use HasFactory<BillingPlanFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'price',
        'duration_minutes',
        'upload_limit',
        'download_limit',
        'data_quota_mb',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_minutes' => 'integer',
            'upload_limit' => 'integer',
            'download_limit' => 'integer',
            'data_quota_mb' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
