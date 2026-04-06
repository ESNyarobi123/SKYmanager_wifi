<?php

namespace App\Support;

use App\Models\Router;

/**
 * High-level readiness flags for admin/support UI (not a second health engine).
 *
 * @return array{
 *     popup_ok: bool,
 *     payment_gateway_ok: bool,
 *     tunnel_ok: bool,
 *     api_ok: bool,
 *     payment_authorize_likely: bool,
 *     production_ready: bool,
 *     bundle_mode: 'bundle'|'legacy'|'unknown',
 *     health_overall: string
 * }
 */
final class RouterOperationalReadiness
{
    public static function snapshot(Router $router): array
    {
        $router->loadMissing('user');
        $customer = $router->user;

        $gatewayConfigured = $customer !== null && $customer->paymentGateways()
            ->where('is_active', true)
            ->whereNotNull('verified_at')
            ->exists();

        $health = is_array($router->health_snapshot) ? $router->health_snapshot : [];
        $overall = (string) ($health['overall'] ?? 'unknown');
        $apiLevel = (string) ($health['api']['level'] ?? 'unknown');
        $tunnelLevel = (string) ($health['tunnel']['level'] ?? 'unknown');
        $portalLevel = (string) ($health['portal']['level'] ?? 'unknown');

        $bundleModeRaw = $router->bundle_deployment_mode;
        $bundleMode = match ($bundleModeRaw) {
            'bundle' => 'bundle',
            'legacy' => 'legacy',
            default => $bundleModeRaw ? 'legacy' : 'unknown',
        };

        $popupOk = match ($bundleMode) {
            'bundle' => (bool) $router->portal_bundle_hash,
            'legacy' => true,
            default => (bool) $router->portal_bundle_hash,
        };

        $tunnelOk = $tunnelLevel === 'healthy'
            || $tunnelLevel === 'tunnel_not_required'
            || $router->last_tunnel_ok === true
            || ($router->preferred_vpn_mode ?? '') === 'none';

        $apiOk = $apiLevel === 'healthy'
            || ($router->last_api_success_at && $router->last_api_success_at->isAfter(now()->subMinutes((int) config('skymanager.health_api_stale_minutes', 45))));

        $onboarding = $router->onboarding_status ?? '';
        $paymentAuthorizeLikely = $gatewayConfigured
            && $apiOk
            && $popupOk
            && in_array($onboarding, [RouterOnboarding::READY, RouterOnboarding::API_OK, RouterOnboarding::PORTAL_OK], true);

        $productionReady = $gatewayConfigured
            && $overall === 'healthy'
            && $onboarding === RouterOnboarding::READY;

        return [
            'popup_ok' => $popupOk,
            'payment_gateway_ok' => $gatewayConfigured,
            'tunnel_ok' => $tunnelOk,
            'api_ok' => $apiOk,
            'payment_authorize_likely' => $paymentAuthorizeLikely,
            'production_ready' => $productionReady,
            'bundle_mode' => $bundleMode,
            'health_overall' => $overall,
            'portal_level' => $portalLevel,
        ];
    }
}
