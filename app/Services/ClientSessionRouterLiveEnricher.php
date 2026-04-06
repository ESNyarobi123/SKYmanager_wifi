<?php

namespace App\Services;

use App\Data\ClientSessionView;
use App\Data\RouterLiveSnapshot;
use App\Models\Router;
use App\Models\RouterHotspotActiveSession;
use App\Support\RouterMacAddress;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Merges RouterOS hotspot active snapshot + stored hotspot payment counters into ClientSessionView rows.
 */
class ClientSessionRouterLiveEnricher
{
    public function enrich(Collection $views, Collection $routerIds): Collection
    {
        if ($routerIds->isEmpty()) {
            return $views;
        }

        $freshSeconds = (int) config('skymanager.router_hotspot_sessions_fresh_seconds', 300);

        $routers = Router::query()->whereIn('id', $routerIds)->get()->keyBy('id');

        $snapshots = RouterHotspotActiveSession::query()
            ->whereIn('router_id', $routerIds)
            ->get()
            ->groupBy('router_id')
            ->map(function (Collection $group) {
                $map = [];
                foreach ($group as $row) {
                    /** @var RouterHotspotActiveSession $row */
                    $mac = RouterMacAddress::normalize($row->mac_address);
                    if ($mac !== null) {
                        $map[$mac] = $row;
                    }
                }

                return $map;
            });

        return $views->map(function (ClientSessionView $v) use ($routers, $snapshots, $freshSeconds) {
            if (! $v->routerId) {
                return $v;
            }

            $router = $routers->get($v->routerId);
            if (! $router) {
                return $v;
            }

            $syncedAt = $router->hotspot_sessions_synced_at;
            $macNorm = RouterMacAddress::normalize($v->clientMac);
            $macMap = $snapshots->get($v->routerId, []);

            $fresh = $syncedAt instanceof CarbonInterface && $syncedAt->gt(now()->subSeconds($freshSeconds));
            $freshnessBase = $syncedAt instanceof CarbonInterface
                ? ($fresh
                    ? __('Last router sync :t ago', ['t' => $syncedAt->diffForHumans(short: true)])
                    : __('Router data stale (sync :t ago)', ['t' => $syncedAt->diffForHumans(short: true)]))
                : '';

            $live = null;

            if ($syncedAt instanceof CarbonInterface && $macNorm !== null && isset($macMap[$macNorm])) {
                /** @var RouterHotspotActiveSession $snap */
                $snap = $macMap[$macNorm];
                $live = new RouterLiveSnapshot(
                    state: $fresh ? 'live_fresh' : 'live_stale',
                    bytesIn: $snap->bytes_in,
                    bytesOut: $snap->bytes_out,
                    uptimeSeconds: $snap->uptime_seconds,
                    uptimeRaw: $snap->uptime_raw,
                    ipAddress: $snap->ip_address,
                    userName: $snap->user_name,
                    syncedAt: $syncedAt,
                    freshnessLabel: $freshnessBase,
                );
            } elseif ($v->sourceType === 'hotspot_payment'
                && $v->liveRouterBytesIn !== null
                && $v->liveRouterBytesOut !== null
                && $v->liveRouterUsageSyncedAt instanceof CarbonInterface) {
                $at = $v->liveRouterUsageSyncedAt;
                $paymentFresh = $at->gt(now()->subSeconds($freshSeconds));
                $live = new RouterLiveSnapshot(
                    state: 'cached_payment',
                    bytesIn: $v->liveRouterBytesIn,
                    bytesOut: $v->liveRouterBytesOut,
                    syncedAt: $at,
                    freshnessLabel: $paymentFresh
                        ? __('Usage from router (stored :t ago)', ['t' => $at->diffForHumans(short: true)])
                        : __('Usage from router (stale, stored :t ago)', ['t' => $at->diffForHumans(short: true)]),
                );
            } elseif ($syncedAt instanceof CarbonInterface && $fresh && $macNorm !== null && $v->segment === 'active' && ($v->isActiveAccess || $v->isPendingAccess)) {
                $live = new RouterLiveSnapshot(
                    state: 'not_listed_fresh',
                    syncedAt: $syncedAt,
                    freshnessLabel: $freshnessBase,
                );
            }

            if ($live === null && $router->hotspot_sessions_sync_error && ! $syncedAt) {
                $live = new RouterLiveSnapshot(
                    state: 'unknown',
                    freshnessLabel: __('Router session sync has not succeeded yet.'),
                );
            }

            if ($live === null) {
                return $v;
            }

            return ClientSessionView::mergeRouterLive($v, $live);
        });
    }
}
