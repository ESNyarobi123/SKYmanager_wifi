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
        'api_credential_version',
        'api_credentials_updated_at',
        'credential_mismatch_suspected',
        'wg_last_handshake_at',
        'last_known_api_username',
        'ztp_api_password',
        'wg_address',
        'hotspot_ssid',
        'hotspot_interface',
        'wan_interface',
        'wifi_interface',
        'preferred_vpn_mode',
        'router_model',
        'routeros_version_hint',
        'use_default_network_settings',
        'bundle_deployment_mode',
        'hotspot_gateway',
        'hotspot_network',
        'is_online',
        'vpn_connected',
        'last_seen',
        'local_portal_token',
        'onboarding_status',
        'claimed_at',
        'script_generated_at',
        'script_downloaded_at',
        'script_applied_at',
        'portal_bundle_version',
        'portal_bundle_hash',
        'portal_folder_name',
        'portal_generated_at',
        'last_api_success_at',
        'last_api_check_at',
        'last_api_error',
        'last_tunnel_check_at',
        'last_tunnel_ok',
        'last_portal_check_at',
        'ready_at',
        'last_error_code',
        'last_error_message',
        'onboarding_warnings',
        'health_snapshot',
        'health_evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'vpn_connected' => 'boolean',
            'last_tunnel_ok' => 'boolean',
            'credential_mismatch_suspected' => 'boolean',
            'use_default_network_settings' => 'boolean',
            'last_seen' => 'datetime',
            'hotspot_sessions_synced_at' => 'datetime',
            'portal_generated_at' => 'datetime',
            'claimed_at' => 'datetime',
            'script_generated_at' => 'datetime',
            'script_downloaded_at' => 'datetime',
            'script_applied_at' => 'datetime',
            'last_api_success_at' => 'datetime',
            'last_api_check_at' => 'datetime',
            'last_tunnel_check_at' => 'datetime',
            'last_portal_check_at' => 'datetime',
            'ready_at' => 'datetime',
            'api_credentials_updated_at' => 'datetime',
            'wg_last_handshake_at' => 'datetime',
            'health_evaluated_at' => 'datetime',
            'api_port' => 'integer',
            'api_credential_version' => 'integer',
            'onboarding_warnings' => 'array',
            'health_snapshot' => 'array',
        ];
    }

    /**
     * Ensure a per-router secret for hardened local-portal mutating requests.
     * Persisted when the customer downloads or previews standalone login.html.
     */
    public function ensureLocalPortalToken(): string
    {
        if ($this->local_portal_token) {
            return $this->local_portal_token;
        }

        $token = bin2hex(random_bytes(24));
        $this->forceFill(['local_portal_token' => $token])->save();

        return $token;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hotspotPayments(): HasMany
    {
        return $this->hasMany(HotspotPayment::class);
    }

    public function hotspotActiveSessions(): HasMany
    {
        return $this->hasMany(RouterHotspotActiveSession::class);
    }

    /**
     * Check if this router is owned by (claimed by) a user.
     */
    public function isClaimed(): bool
    {
        return $this->user_id !== null;
    }
}
