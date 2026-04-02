<?php

namespace App\Models;

use Database\Factories\RouterFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Router extends Model
{
    /** @use HasFactory<RouterFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'mac_address',
        'name',
        'ip_address',
        'api_port',
        'api_username',
        'api_password',
        'ztp_api_password',
        'wg_address',
        'hotspot_ssid',
        'hotspot_interface',
        'hotspot_gateway',
        'hotspot_network',
        'is_online',
        'vpn_connected',
        'last_seen',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'vpn_connected' => 'boolean',
            'last_seen' => 'datetime',
            'api_port' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Check if this router is owned by (claimed by) a user.
     */
    public function isClaimed(): bool
    {
        return $this->user_id !== null;
    }
}
