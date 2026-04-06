<?php

namespace App\Support;

use App\Models\Router;

/**
 * Central rules for when server + router WireGuard inputs are complete enough
 * to emit a real RouterOS WG block (not a misleading partial config).
 */
final class WireguardProvisioning
{
    /**
     * @return list<string> Human-readable keys missing from server .env / config (for warnings).
     */
    public static function missingServerEnvComponents(): array
    {
        $missing = [];
        $endpoint = trim((string) config('services.wireguard.vps_endpoint', ''));
        $pub = trim((string) config('services.wireguard.vps_public_key', ''));
        $port = (int) config('services.wireguard.listen_port', 51820);
        $subnet = trim((string) config('services.wireguard.api_subnet', ''));

        if ($endpoint === '') {
            $missing[] = 'WG_VPS_ENDPOINT';
        }
        if ($pub === '') {
            $missing[] = 'WG_VPS_PUBLIC_KEY';
        }
        if ($port < 1 || $port > 65535) {
            $missing[] = 'WG_LISTEN_PORT (must be 1–65535)';
        }
        if ($subnet === '' || ! self::isValidIpv4Cidr($subnet)) {
            $missing[] = 'WG_API_SUBNET (valid IPv4 CIDR, e.g. 10.10.0.0/24)';
        }

        return $missing;
    }

    public static function isServerConfigComplete(): bool
    {
        return self::missingServerEnvComponents() === [];
    }

    /**
     * Router-side tunnel IP: must be explicitly set (no silent default) when WG is required.
     */
    public static function isRouterWgAddressUsable(?string $wgAddress): bool
    {
        if ($wgAddress === null) {
            return false;
        }

        $addr = trim($wgAddress);
        if ($addr === '' || str_contains(strtoupper($addr), 'X')) {
            return false;
        }

        if (! self::isValidTunnelAddress($addr)) {
            return false;
        }

        $subnet = trim((string) config('services.wireguard.api_subnet', ''));
        if ($subnet !== '' && self::isValidIpv4Cidr($subnet)) {
            return self::ipv4InCidr(self::ipv4Only($addr), $subnet);
        }

        return true;
    }

    public static function vpsInterfaceName(): string
    {
        $name = trim((string) config('services.wireguard.vps_interface_name', 'wg0'));

        return $name !== '' ? $name : 'wg0';
    }

    public static function listenPort(): int
    {
        return (int) config('services.wireguard.listen_port', 51820);
    }

    /**
     * @return list<string> Missing pieces for generating a usable WG section (server + router).
     */
    public static function missingForFullWireguardBlock(Router $router): array
    {
        $missing = [];
        foreach (self::missingServerEnvComponents() as $m) {
            $missing[] = $m;
        }
        if (! self::isRouterWgAddressUsable($router->wg_address)) {
            $missing[] = 'router wg_address (set in Advanced when claiming, or enable WG_AUTO_ASSIGN_IPS)';
        }

        return $missing;
    }

    public static function shouldGenerateWireguardSection(Router $router): bool
    {
        $vpnMode = $router->preferred_vpn_mode ?? 'wireguard';

        return match ($vpnMode) {
            'none' => false,
            'auto' => self::isServerConfigComplete() && self::isRouterWgAddressUsable($router->wg_address),
            default => self::isServerConfigComplete() && self::isRouterWgAddressUsable($router->wg_address),
        };
    }

    public static function wireguardHardRequiredButIncomplete(Router $router): bool
    {
        return ($router->preferred_vpn_mode ?? 'wireguard') === 'wireguard'
            && ! self::shouldGenerateWireguardSection($router);
    }

    public static function preciseWireguardWarningMessage(Router $router): string
    {
        $missing = self::missingForFullWireguardBlock($router);

        return 'WireGuard is required (VPN mode wireguard) but configuration is incomplete. Missing: '.implode(', ', $missing).'. Fix .env / server config and set the router tunnel IP, then regenerate the script.';
    }

    private static function isValidIpv4Cidr(string $cidr): bool
    {
        if (! preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})/(\d{1,2})$#', $cidr, $m)) {
            return false;
        }

        for ($i = 1; $i <= 4; $i++) {
            $o = (int) $m[$i];
            if ($o < 0 || $o > 255) {
                return false;
            }
        }

        $bits = (int) $m[5];

        return $bits >= 0 && $bits <= 32;
    }

    private static function isValidTunnelAddress(string $addrWithCidr): bool
    {
        if (! preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})(/\d{1,2})?$#', $addrWithCidr, $m)) {
            return false;
        }

        for ($i = 1; $i <= 4; $i++) {
            $o = (int) $m[$i];
            if ($o < 0 || $o > 255) {
                return false;
            }
        }

        if (isset($m[5]) && $m[5] !== '') {
            $bits = (int) substr($m[5], 1);
            if ($bits < 0 || $bits > 32) {
                return false;
            }
        }

        return true;
    }

    private static function ipv4Only(string $addrWithCidr): string
    {
        return explode('/', $addrWithCidr, 2)[0];
    }

    private static function ipv4InCidr(string $ip, string $cidr): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        [$net, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        $ipLong = ip2long($ip);
        $netLong = ip2long($net);
        if ($ipLong === false || $netLong === false) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (~((1 << (32 - $bits)) - 1)) & 0xFFFFFFFF;

        return ($ipLong & $mask) === ($netLong & $mask);
    }
}
