<?php

namespace App\Models;

use Database\Factories\RouterFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Router extends Model
{
    /** @use HasFactory<RouterFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'ip_address',
        'api_port',
        'api_username',
        'api_password',
        'ztp_api_password',
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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
