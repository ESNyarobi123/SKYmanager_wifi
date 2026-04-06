<?php

namespace App\Support;

/**
 * Short onboarding_status values (≤32 chars for legacy DB column).
 */
final class RouterOnboarding
{
    public const CLAIMED = 'claimed';

    public const SCRIPT_GENERATED = 'script_generated';

    public const SCRIPT_DOWNLOADED = 'script_downloaded';

    public const SCRIPT_PENDING = 'script_pending';

    public const TUNNEL_PENDING = 'tunnel_pending';

    public const TUNNEL_OK = 'tunnel_ok';

    public const API_PENDING = 'api_pending';

    public const API_OK = 'api_ok';

    public const PORTAL_PENDING = 'portal_pending';

    public const PORTAL_OK = 'portal_ok';

    public const READY = 'ready';

    public const DEGRADED = 'degraded';

    public const OFFLINE = 'offline';

    public const CRED_MISMATCH = 'cred_mismatch';

    public const BUNDLE_MISMATCH = 'bundle_mismatch';

    public const ERROR = 'error';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::CLAIMED => 'Registered — setup not started',
            self::SCRIPT_GENERATED => 'Setup script generated',
            self::SCRIPT_DOWNLOADED => 'Script downloaded',
            self::SCRIPT_PENDING => 'Waiting for router to apply script',
            self::TUNNEL_PENDING => 'VPN tunnel not up yet',
            self::TUNNEL_OK => 'VPN tunnel reachable',
            self::API_PENDING => 'API not verified',
            self::API_OK => 'API login verified',
            self::PORTAL_PENDING => 'Captive portal bundle not verified',
            self::PORTAL_OK => 'Portal bundle verified on router',
            self::READY => 'Ready for payments & hotspot',
            self::DEGRADED => 'Working with issues',
            self::OFFLINE => 'Unreachable',
            self::CRED_MISMATCH => 'API credentials may be out of sync',
            self::BUNDLE_MISMATCH => 'Hotspot bundle mismatch',
            self::ERROR => 'Configuration error — check messages',
        ];
    }

    public static function label(string $status): string
    {
        return self::labels()[$status] ?? $status;
    }
}
