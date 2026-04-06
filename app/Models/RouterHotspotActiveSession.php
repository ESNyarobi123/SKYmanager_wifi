<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterHotspotActiveSession extends Model
{
    protected $fillable = [
        'router_id',
        'mikrotik_internal_id',
        'mac_address',
        'ip_address',
        'user_name',
        'bytes_in',
        'bytes_out',
        'uptime_seconds',
        'uptime_raw',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'bytes_in' => 'integer',
            'bytes_out' => 'integer',
            'uptime_seconds' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
