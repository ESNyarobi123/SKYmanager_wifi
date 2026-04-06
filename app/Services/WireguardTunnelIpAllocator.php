<?php

namespace App\Services;

use App\Models\Router;
use App\Support\WireguardProvisioning;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WireguardTunnelIpAllocator
{
    /**
     * Pick a free /32 inside WG_API_SUBNET (/24 only). Deterministic rotation by router id.
     *
     * @throws RuntimeException
     */
    public function allocateForRouter(Router $router): string
    {
        if (! config('services.wireguard.auto_assign_router_ips')) {
            throw new RuntimeException('WG_AUTO_ASSIGN_IPS is disabled.');
        }

        if (! WireguardProvisioning::isServerConfigComplete()) {
            throw new RuntimeException('WireGuard server env incomplete; cannot auto-assign from subnet.');
        }

        if (WireguardProvisioning::isRouterWgAddressUsable($router->wg_address)) {
            return (string) $router->wg_address;
        }

        $subnet = (string) config('services.wireguard.api_subnet', '');
        if (! preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})/(\d+)$#', $subnet, $m)) {
            throw new RuntimeException('WG_API_SUBNET is not parseable for auto-assign.');
        }

        $bits = (int) $m[5];
        if ($bits !== 24) {
            throw new RuntimeException('Auto-assign currently supports /24 WG_API_SUBNET only.');
        }

        $prefix = $m[1].'.'.$m[2].'.'.$m[3].'.';

        return DB::transaction(function () use ($router, $prefix) {
            $candidates = range(10, 253);
            $start = abs(crc32((string) $router->id)) % count($candidates);
            $ordered = array_merge(
                array_slice($candidates, $start),
                array_slice($candidates, 0, $start)
            );

            $usedOctets = Router::query()
                ->whereNotNull('wg_address')
                ->lockForUpdate()
                ->pluck('wg_address')
                ->map(fn ($a) => self::lastOctet((string) $a))
                ->filter()
                ->all();

            $usedMap = array_fill_keys($usedOctets, true);

            foreach ($ordered as $octet) {
                if (! empty($usedMap[$octet])) {
                    continue;
                }

                $candidate = $prefix.$octet.'/32';

                $exists = Router::query()
                    ->where('wg_address', $candidate)
                    ->whereKeyNot($router->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                if (! WireguardProvisioning::isRouterWgAddressUsable($candidate)) {
                    continue;
                }

                return $candidate;
            }

            throw new RuntimeException('No free /32 address in WG_API_SUBNET pool.');
        });
    }

    private static function lastOctet(string $addr): ?int
    {
        if (! preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})(/\d+)?$#', trim($addr), $m)) {
            return null;
        }

        return (int) $m[4];
    }
}
