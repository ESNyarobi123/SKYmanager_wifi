<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Standalone captive portal bundle
    |--------------------------------------------------------------------------
    | Bumped when portal HTML/JS contract changes. Embedded in generated
    | login.html so operators can confirm routers run the expected build.
    */
    'portal_bundle_version' => env('SKYMANAGER_PORTAL_BUNDLE_VERSION', '2026.04.06.1'),

    /*
    |--------------------------------------------------------------------------
    | Hotspot payment authorization
    |--------------------------------------------------------------------------
    */
    'hotspot_authorize_max_attempts' => (int) env('SKYMANAGER_HOTSPOT_AUTH_MAX_ATTEMPTS', 30),

    /*
    |--------------------------------------------------------------------------
    | Router health evaluation
    |--------------------------------------------------------------------------
    */
    'health_api_stale_minutes' => (int) env('SKYMANAGER_HEALTH_API_STALE_MINUTES', 45),

    /*
    |--------------------------------------------------------------------------
    | RouterOS hotspot active session polling
    |--------------------------------------------------------------------------
    | Data newer than this many seconds is labeled "live" / fresh in customer UI.
    | Scheduled sync is opt-in via SKYMANAGER_SCHEDULE_HOTSPOT_SESSION_SYNC.
    */
    'router_hotspot_sessions_fresh_seconds' => (int) env('SKYMANAGER_ROUTER_HOTSPOT_SESSIONS_FRESH_SECONDS', 300),

    'schedule_router_hotspot_sessions_sync' => filter_var(
        env('SKYMANAGER_SCHEDULE_HOTSPOT_SESSION_SYNC', false),
        FILTER_VALIDATE_BOOL
    ),

    /** Milliseconds to sleep between routers when syncing in batch (API load). */
    'router_hotspot_sessions_sync_sleep_ms' => (int) env('SKYMANAGER_ROUTER_HOTSPOT_SESSION_SYNC_SLEEP_MS', 250),

    /*
    |--------------------------------------------------------------------------
    | WireGuard (optional override for script footer / operator hints)
    |--------------------------------------------------------------------------
    | Primary keys live in config/services.php (WG_*). Set this only if you need
    | a skymanager-specific override without changing services.wireguard.
    */
    'wireguard' => [
        'vps_interface_name' => (string) (env('SKYMANAGER_WG_SCRIPT_VPS_INTERFACE') ?? ''),
    ],

];
