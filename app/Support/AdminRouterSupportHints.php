<?php

namespace App\Support;

use App\Models\Router;

/**
 * Human-readable explanations for support staff (maps real router state → copy).
 */
final class AdminRouterSupportHints
{
    /**
     * @return list<string>
     */
    public static function forRouter(Router $router): array
    {
        $hints = [];
        $readiness = RouterOperationalReadiness::snapshot($router);
        $status = $router->onboarding_status ?? '';

        if ($readiness['bundle_mode'] === 'legacy' || ($readiness['bundle_mode'] === 'unknown' && ! $router->portal_bundle_hash)) {
            $hints[] = __('Router is still on or eligible for legacy single-file captive portal flow — recommend bundle migration for reliable popups.');
        }

        if ($status === RouterOnboarding::CLAIMED && ! $router->script_generated_at) {
            $hints[] = __('Router is only registered — no setup script has been generated yet.');
        }

        if (in_array($status, [RouterOnboarding::SCRIPT_PENDING, RouterOnboarding::SCRIPT_DOWNLOADED, RouterOnboarding::SCRIPT_GENERATED], true)) {
            $hints[] = __('Customer may not have finished pasting the script, or health checks have not run since apply.');
        }

        if (($router->preferred_vpn_mode ?? '') !== 'none' && $router->last_tunnel_ok === false) {
            $hints[] = __('Tunnel check last failed — WireGuard may be down or handshake stale.');
        }

        if ($router->wg_last_handshake_at && $router->wg_last_handshake_at->lt(now()->subHours(2)) && ($router->preferred_vpn_mode ?? '') === 'wireguard') {
            $hints[] = __('No recent WireGuard handshake — verify peer on VPS and router clock.');
        }

        if ($router->credential_mismatch_suspected || $status === RouterOnboarding::CRED_MISMATCH) {
            $hints[] = __('API credentials may be out of sync with the router — use rotate/repair and a fresh setup script.');
        }

        if ($readiness['popup_ok'] && ! $readiness['payment_authorize_likely']) {
            $hints[] = __('Captive portal files may work, but payment authorization is still blocked (API, gateway, or onboarding not ready).');
        }

        if (! $readiness['payment_gateway_ok']) {
            $hints[] = __('Customer has no verified active payment gateway — payments cannot complete.');
        }

        if ($status === RouterOnboarding::BUNDLE_MISMATCH) {
            $hints[] = __('Hotspot bundle on the router does not match VPS — regenerate bundle metadata and full setup script.');
        }

        if ($readiness['health_overall'] === 'error') {
            $hints[] = __('Overall health is error — review tunnel, API, and portal cards plus last error message.');
        }

        if ($hints === []) {
            $hints[] = __('No automated hints — use health probe and diagnostics below.');
        }

        return $hints;
    }
}
