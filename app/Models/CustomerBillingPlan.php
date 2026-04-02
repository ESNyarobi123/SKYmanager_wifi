<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBillingPlan extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'customer_id',
        'name',
        'price',
        'duration_minutes',
        'data_quota_mb',
        'upload_speed_kbps',
        'download_speed_kbps',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_minutes' => 'integer',
            'data_quota_mb' => 'integer',
            'upload_speed_kbps' => 'integer',
            'download_speed_kbps' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Human-readable duration string (e.g. "1 Hour", "30 Minutes", "7 Days").
     */
    public function durationLabel(): string
    {
        $minutes = $this->duration_minutes;

        if ($minutes >= 1440 && $minutes % 1440 === 0) {
            $days = $minutes / 1440;

            return $days === 1 ? '1 Day' : "{$days} Days";
        }

        if ($minutes >= 60 && $minutes % 60 === 0) {
            $hours = $minutes / 60;

            return $hours === 1 ? '1 Hour' : "{$hours} Hours";
        }

        return $minutes === 1 ? '1 Minute' : "{$minutes} Minutes";
    }

    /**
     * Speed label (e.g. "5 Mbps / 10 Mbps" or "Unlimited").
     */
    public function speedLabel(): string
    {
        if (! $this->upload_speed_kbps && ! $this->download_speed_kbps) {
            return 'Unlimited';
        }

        $up = $this->upload_speed_kbps ? round($this->upload_speed_kbps / 1024, 1).'M' : '∞';
        $down = $this->download_speed_kbps ? round($this->download_speed_kbps / 1024, 1).'M' : '∞';

        return "↑{$up} / ↓{$down}";
    }

    /**
     * MikroTik rate-limit string for /ip hotspot user profile ("Xk/Yk" format).
     */
    public function mikrotikRateLimit(): string
    {
        $up = $this->upload_speed_kbps ? $this->upload_speed_kbps.'k' : '0';
        $down = $this->download_speed_kbps ? $this->download_speed_kbps.'k' : '0';

        return "{$up}/{$down}";
    }
}
