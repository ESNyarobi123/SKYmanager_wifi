<?php

namespace App\Services;

use App\Models\HotspotPayment;
use App\Models\Router;
use App\Support\RouterOnboarding;

/**
 * Single source for admin dashboard + operations incident counts (avoid duplicated queries).
 */
class PaymentIncidentSummaryService
{
    /**
     * @param  string|null  $routerOwnerUserId  When set, only routers (and related hotspot payments) owned by this user.
     * @return array<string, int>
     */
    public function summarize(?string $routerOwnerUserId = null): array
    {
        $maxAttempts = (int) config('skymanager.hotspot_authorize_max_attempts', 30);

        $routers = Router::query();
        if ($routerOwnerUserId !== null) {
            $routers->where('user_id', $routerOwnerUserId);
        }

        $routerIds = null;
        if ($routerOwnerUserId !== null) {
            $routerIds = (clone $routers)->pluck('id')->all();
        }

        $hotspot = HotspotPayment::query();
        if ($routerIds !== null) {
            if ($routerIds === []) {
                $hotspot->whereRaw('1 = 0');
            } else {
                $hotspot->whereIn('router_id', $routerIds);
            }
        }

        return [
            'routers_long_claimed' => (clone $routers)
                ->where('onboarding_status', RouterOnboarding::CLAIMED)
                ->where('created_at', '<', now()->subHours(72))
                ->count(),
            'routers_tunnel_stuck' => (clone $routers)
                ->where('onboarding_status', RouterOnboarding::TUNNEL_PENDING)
                ->where('updated_at', '<', now()->subHours(24))
                ->count(),
            'routers_cred_flags' => (clone $routers)->where('credential_mismatch_suspected', true)->count(),
            'routers_bundle_mismatch' => (clone $routers)->where('onboarding_status', RouterOnboarding::BUNDLE_MISMATCH)->count(),
            'routers_offline_status' => (clone $routers)->where('onboarding_status', RouterOnboarding::OFFLINE)->count(),
            'hotspot_stuck_authorizing' => (clone $hotspot)
                ->where('status', 'success')
                ->whereNull('authorized_at')
                ->where(function ($q) {
                    $q->whereNotNull('last_authorize_error')
                        ->orWhere('authorize_attempts', '>', 0);
                })
                ->count(),
            'hotspot_retry_exhausted' => (clone $hotspot)
                ->where('status', 'success')
                ->whereNull('authorized_at')
                ->where(function ($q) use ($maxAttempts) {
                    $q->where('authorize_attempts', '>=', $maxAttempts)
                        ->orWhereNotNull('authorize_retry_exhausted_at');
                })
                ->count(),
            'hotspot_provider_confirmed_not_authorized' => (clone $hotspot)
                ->where('status', 'success')
                ->whereNotNull('provider_confirmed_at')
                ->whereNull('authorized_at')
                ->count(),
            'hotspot_authorize_failures_24h' => (clone $hotspot)
                ->whereNotNull('last_authorize_failed_at')
                ->where('last_authorize_failed_at', '>=', now()->subDay())
                ->count(),
        ];
    }
}
