<?php

namespace App\Services;

use App\Models\Router;
use App\Support\RouterOnboarding;
use App\Support\WireguardProvisioning;

class RouterHealthService
{
    public function __construct(
        private MikrotikApiService $mikrotik,
        private RouterOnboardingService $onboarding,
    ) {}

    /**
     * @return array{
     *     overall: string,
     *     onboarding: array{level: string, code: string, detail: string},
     *     tunnel: array{level: string, code: string, detail: string},
     *     api: array{level: string, code: string, detail: string},
     *     portal: array{level: string, code: string, detail: string},
     *     suggested_onboarding_status: string
     * }
     */
    public function evaluate(Router $router, bool $probeLive = false): array
    {
        $wgRequired = $this->isWireguardRequired($router);
        $wgEnvOk = WireguardProvisioning::isServerConfigComplete();

        $onboardingDim = $this->evaluateOnboardingDimension($router, $wgRequired, $wgEnvOk);
        $tunnel = $this->evaluateTunnel($router, $wgEnvOk);
        $api = $this->evaluateApi($router, $probeLive);
        $portal = $this->evaluatePortal($router, $probeLive);

        $overall = $this->mergeOverall($tunnel, $api, $portal, $onboardingDim);

        $suggested = $this->suggestStatus($router, $wgRequired, $wgEnvOk, $tunnel, $api, $portal, $probeLive);

        return [
            'overall' => $overall,
            'onboarding' => $onboardingDim,
            'tunnel' => $tunnel,
            'api' => $api,
            'portal' => $portal,
            'suggested_onboarding_status' => $suggested,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function persist(Router $router, bool $probeLive = false): array
    {
        $report = $this->evaluate($router, $probeLive);
        $this->onboarding->applyHealthEvaluation($router->fresh(), $report, $report['suggested_onboarding_status']);

        return $report;
    }

    private function isWireguardRequired(Router $router): bool
    {
        return in_array($router->preferred_vpn_mode ?? 'wireguard', ['wireguard', 'auto'], true);
    }

    /**
     * @return array{level: string, code: string, detail: string}
     */
    private function evaluateOnboardingDimension(Router $router, bool $wgRequired, bool $wgEnvOk): array
    {
        if ($wgRequired && ! $wgEnvOk) {
            $missing = implode(', ', WireguardProvisioning::missingServerEnvComponents());

            return ['level' => 'error', 'code' => 'wg_env_missing', 'detail' => 'WireGuard is required but server WG env is incomplete. Missing: '.$missing.'.'];
        }

        if (($router->preferred_vpn_mode ?? '') === 'wireguard'
            && $wgEnvOk
            && ! WireguardProvisioning::isRouterWgAddressUsable($router->wg_address)) {
            return [
                'level' => 'error',
                'code' => 'wg_tunnel_ip_missing',
                'detail' => 'WireGuard mode requires a usable wg_address (tunnel IP /32 inside WG_API_SUBNET). Set it in Advanced when claiming or enable WG_AUTO_ASSIGN_IPS.',
            ];
        }

        if ($router->onboarding_status === RouterOnboarding::ERROR) {
            return ['level' => 'error', 'code' => 'onboarding_error', 'detail' => (string) ($router->last_error_message ?? 'See last_error_message on router.')];
        }

        return ['level' => 'healthy', 'code' => 'onboarding_ok', 'detail' => 'No blocking onboarding errors recorded.'];
    }

    /**
     * @return array{level: string, code: string, detail: string}
     */
    private function evaluateTunnel(Router $router, bool $wgEnvOk): array
    {
        if (($router->preferred_vpn_mode ?? '') === 'none') {
            return ['level' => 'healthy', 'code' => 'tunnel_not_required', 'detail' => 'VPN not required for this router.'];
        }

        if (($router->preferred_vpn_mode ?? '') === 'auto' && $wgEnvOk && ! WireguardProvisioning::isRouterWgAddressUsable($router->wg_address)) {
            return [
                'level' => 'healthy',
                'code' => 'tunnel_auto_skipped_no_client_ip',
                'detail' => 'Auto mode without wg_address — hotspot script runs without WireGuard until you assign a tunnel IP.',
            ];
        }

        if (! $wgEnvOk) {
            return [
                'level' => 'error',
                'code' => 'wg_config_incomplete',
                'detail' => 'Server-side WireGuard configuration is incomplete. Missing: '.implode(', ', WireguardProvisioning::missingServerEnvComponents()).'.',
            ];
        }

        if (($router->preferred_vpn_mode ?? '') === 'wireguard' && ! WireguardProvisioning::isRouterWgAddressUsable($router->wg_address)) {
            return [
                'level' => 'error',
                'code' => 'wg_address_missing',
                'detail' => 'WireGuard tunnel IP (wg_address) is missing or invalid. Set it in Advanced when claiming or use WG_AUTO_ASSIGN_IPS.',
            ];
        }

        if (! WireguardProvisioning::shouldGenerateWireguardSection($router)) {
            return ['level' => 'healthy', 'code' => 'tunnel_wg_section_off', 'detail' => 'WireGuard block not used for this router (mode / inputs).'];
        }

        if ($router->last_tunnel_ok === true) {
            $detail = 'Last tunnel check succeeded.';
            if ($router->wg_last_handshake_at) {
                $detail .= ' Last WG handshake: '.$router->wg_last_handshake_at->diffForHumans().'.';
            }

            return ['level' => 'healthy', 'code' => 'tunnel_ok', 'detail' => $detail];
        }

        if ($router->last_tunnel_ok === false) {
            return ['level' => 'error', 'code' => 'tunnel_down', 'detail' => (string) ($router->last_api_error ?? 'Tunnel/API check failed.')];
        }

        return ['level' => 'unknown', 'code' => 'tunnel_unknown', 'detail' => 'Run a health check to verify the tunnel.'];
    }

    /**
     * @return array{level: string, code: string, detail: string}
     */
    private function evaluateApi(Router $router, bool $probeLive): array
    {
        $recentMins = (int) config('skymanager.health_api_stale_minutes', 45);

        if ($probeLive) {
            try {
                try {
                    $this->mikrotik->connectZtp($router);
                    try {
                        $this->mikrotik->getSystemResources();
                        $probe = $this->mikrotik->probeTunnelUpWhileConnected($router);
                        $tunnelUp = $probe['tunnel_up'];
                        $handshake = $probe['handshake_at'];
                    } finally {
                        $this->mikrotik->disconnect();
                    }

                    $router->forceFill([
                        'last_api_success_at' => now(),
                        'last_api_check_at' => now(),
                        'last_api_error' => null,
                        'last_known_api_username' => $router->api_username ?: 'sky-api',
                        'last_tunnel_check_at' => now(),
                        'last_tunnel_ok' => $tunnelUp,
                        'vpn_connected' => $tunnelUp,
                        'wg_last_handshake_at' => $handshake,
                        'is_online' => true,
                        'credential_mismatch_suspected' => false,
                    ])->save();

                    return ['level' => 'healthy', 'code' => 'api_ok', 'detail' => 'Live API probe succeeded.'];
                } catch (\Throwable $e) {
                    $msg = $e->getMessage();
                    $cred = $this->looksLikeCredentialFailure($msg);

                    $router->forceFill([
                        'last_api_check_at' => now(),
                        'last_api_error' => $msg,
                        'credential_mismatch_suspected' => $cred,
                        'is_online' => false,
                    ])->save();

                    return [
                        'level' => 'error',
                        'code' => $cred ? 'api_auth_failed' : 'api_unreachable',
                        'detail' => $msg,
                    ];
                }
            } finally {
                try {
                    $this->mikrotik->disconnect();
                } catch (\Throwable) {
                }
            }
        }

        if ($router->credential_mismatch_suspected) {
            return ['level' => 'error', 'code' => 'cred_mismatch', 'detail' => 'Credential mismatch flagged — rotate password or re-run setup script.'];
        }

        if ($router->last_api_success_at && $router->last_api_success_at->gt(now()->subMinutes($recentMins))) {
            return ['level' => 'healthy', 'code' => 'api_ok', 'detail' => 'Recent successful API check.'];
        }

        return ['level' => 'unknown', 'code' => 'api_unknown', 'detail' => 'Run php artisan app:router-health --probe to verify API.'];
    }

    /**
     * @return array{level: string, code: string, detail: string}
     */
    private function evaluatePortal(Router $router, bool $probeLive): array
    {
        if ($router->bundle_deployment_mode === 'legacy') {
            return ['level' => 'warning', 'code' => 'portal_legacy', 'detail' => 'Legacy single-file captive portal path.'];
        }

        if (! $router->portal_bundle_hash) {
            return ['level' => 'warning', 'code' => 'portal_not_built', 'detail' => 'Generate setup script or open hotspot bundle page to build metadata.'];
        }

        if (! $probeLive) {
            return ['level' => 'unknown', 'code' => 'portal_unknown', 'detail' => 'Bundle registered on VPS; filesystem not verified on router.'];
        }

        try {
            $v = $this->mikrotik->verifyHotspotBundle($router);
            $router->forceFill(['last_portal_check_at' => now()])->save();

            if ($v['ok']) {
                return ['level' => 'healthy', 'code' => 'portal_ok', 'detail' => 'All bundle files and html-directory look correct.'];
            }

            return ['level' => 'error', 'code' => 'portal_incomplete', 'detail' => implode('; ', $v['issues'])];
        } catch (\Throwable $e) {
            return ['level' => 'error', 'code' => 'portal_check_failed', 'detail' => $e->getMessage()];
        }
    }

    private function looksLikeCredentialFailure(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'invalid user')
            || str_contains($m, 'invalid password')
            || str_contains($m, 'authentication failed')
            || str_contains($m, 'cannot log in');
    }

    /**
     * @param  array{level: string, code: string, detail: string}  $tunnel
     * @param  array{level: string, code: string, detail: string}  $api
     * @param  array{level: string, code: string, detail: string}  $portal
     * @param  array{level: string, code: string, detail: string}  $onboardingDim
     */
    private function mergeOverall(array $tunnel, array $api, array $portal, array $onboardingDim): string
    {
        foreach ([$onboardingDim, $tunnel, $api, $portal] as $dim) {
            if ($dim['level'] === 'error') {
                return 'error';
            }
        }

        foreach ([$tunnel, $api, $portal] as $dim) {
            if ($dim['level'] === 'unknown') {
                return 'unknown';
            }
        }

        foreach ([$tunnel, $api, $portal] as $dim) {
            if ($dim['level'] === 'warning') {
                return 'warning';
            }
        }

        return 'healthy';
    }

    /**
     * @param  array{level: string, code: string, detail: string}  $tunnel
     * @param  array{level: string, code: string, detail: string}  $api
     * @param  array{level: string, code: string, detail: string}  $portal
     */
    private function suggestStatus(
        Router $router,
        bool $wgRequired,
        bool $wgEnvOk,
        array $tunnel,
        array $api,
        array $portal,
        bool $probeLive
    ): string {
        if ($wgRequired && ! $wgEnvOk) {
            return RouterOnboarding::ERROR;
        }

        if (in_array($tunnel['code'], ['wg_address_missing', 'wg_config_incomplete'], true)) {
            return RouterOnboarding::ERROR;
        }

        if ($router->onboarding_status === RouterOnboarding::ERROR && $router->last_error_code === 'wg_required_missing') {
            return RouterOnboarding::ERROR;
        }

        if ($api['code'] === 'api_auth_failed' || ($router->credential_mismatch_suspected && $api['level'] !== 'healthy')) {
            return RouterOnboarding::CRED_MISMATCH;
        }

        if ($probeLive && $portal['code'] === 'portal_incomplete') {
            return RouterOnboarding::BUNDLE_MISMATCH;
        }

        if ($tunnel['level'] === 'error' && $wgRequired) {
            return RouterOnboarding::OFFLINE;
        }

        if ($api['level'] === 'healthy' && ($tunnel['level'] === 'healthy' || $tunnel['code'] === 'tunnel_not_required')) {
            if ($portal['level'] === 'healthy') {
                return RouterOnboarding::READY;
            }

            if ($portal['code'] === 'portal_unknown' || $portal['code'] === 'portal_not_built') {
                return RouterOnboarding::PORTAL_PENDING;
            }

            if ($portal['level'] === 'warning' && $portal['code'] === 'portal_legacy') {
                return RouterOnboarding::DEGRADED;
            }
        }

        if ($api['level'] === 'healthy' && $tunnel['code'] === 'tunnel_unknown' && $wgRequired) {
            return RouterOnboarding::TUNNEL_PENDING;
        }

        if ($api['level'] === 'unknown' && $router->script_generated_at) {
            return RouterOnboarding::API_PENDING;
        }

        if (in_array($router->onboarding_status, [RouterOnboarding::SCRIPT_PENDING, RouterOnboarding::SCRIPT_DOWNLOADED], true)) {
            return $router->onboarding_status;
        }

        if ($router->script_generated_at && ! $router->last_api_success_at) {
            return RouterOnboarding::SCRIPT_PENDING;
        }

        if ($router->script_generated_at) {
            return RouterOnboarding::API_PENDING;
        }

        return $router->onboarding_status ?: RouterOnboarding::CLAIMED;
    }
}
