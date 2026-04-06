<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Router;
use App\Models\User;
use App\Support\RouterOnboarding;
use Illuminate\Support\Facades\Auth;

class AdminRouterRepairService
{
    public function __construct(
        private readonly RouterHealthService $health,
        private readonly MikrotikApiService $mikrotik,
        private readonly HotspotBundleService $bundles,
        private readonly RouterOnboardingService $onboarding,
        private readonly RouterCredentialSyncService $credentials,
    ) {}

    /**
     * @return array{ok: bool, message: string, report?: array<string, mixed>}
     */
    public function recalculateHealth(Router $router, bool $probe): array
    {
        try {
            $report = $this->health->persist($router->fresh(), $probe);

            $this->log('Router health recalculated', $router, [
                'probe' => $probe,
                'overall' => $report['overall'] ?? null,
                'suggested_onboarding' => $report['suggested_onboarding_status'] ?? null,
            ]);

            return [
                'ok' => true,
                'message' => __('Health updated: overall :o, onboarding :s.', [
                    'o' => $report['overall'] ?? '—',
                    's' => $router->fresh()->onboarding_status ?? '—',
                ]),
                'report' => $report,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string, script?: string}
     */
    public function regenerateFullSetupScript(Router $router, bool $rotateCredentials = false): array
    {
        try {
            $script = $this->mikrotik->generateFullSetupScript($router->fresh(), $rotateCredentials);
            $this->log('Full setup script regenerated from admin', $router, [
                'rotate_credentials' => $rotateCredentials,
            ]);

            return ['ok' => true, 'message' => __('Setup script regenerated.'), 'script' => $script];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function regenerateHotspotBundle(Router $router): array
    {
        try {
            $customer = $router->user;
            if (! $customer instanceof User) {
                return ['ok' => false, 'message' => __('Router has no customer owner — cannot sync bundle.')];
            }

            $router->ensureLocalPortalToken();
            $this->bundles->syncBundleMetadata($router->fresh(), $customer);
            $this->log('Hotspot bundle metadata synced from admin', $router, []);

            return ['ok' => true, 'message' => __('Bundle metadata refreshed on VPS.')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string, report?: array<string, mixed>}
     */
    public function verifyOnboarding(Router $router, bool $probe): array
    {
        $report = $this->health->evaluate($router->fresh(), $probe);
        $this->log('Router onboarding verified (evaluate only)', $router, [
            'probe' => $probe,
            'suggested' => $report['suggested_onboarding_status'] ?? null,
        ]);

        return [
            'ok' => true,
            'message' => __('Evaluate complete (not persisted). Suggested: :s', ['s' => $report['suggested_onboarding_status'] ?? '—']),
            'report' => $report,
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function syncTunnelState(Router $router): array
    {
        try {
            $this->mikrotik->connectZtp($router);
            try {
                $probe = $this->mikrotik->probeTunnelUpWhileConnected($router);
                $up = $probe['tunnel_up'];
                $handshake = $probe['handshake_at'];
            } finally {
                $this->mikrotik->disconnect();
            }

            $router->forceFill([
                'last_tunnel_check_at' => now(),
                'last_tunnel_ok' => $up,
                'vpn_connected' => $up,
                'wg_last_handshake_at' => $handshake,
            ])->save();

            $this->log('Tunnel state synced from admin', $router, ['tunnel_up' => $up]);

            return ['ok' => true, 'message' => $up ? __('Tunnel reports up.') : __('Tunnel reports down.')];
        } catch (\Throwable $e) {
            try {
                $this->mikrotik->disconnect();
            } catch (\Throwable) {
            }

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function probeApiAuth(Router $router): array
    {
        return $this->recalculateHealth($router, true);
    }

    /**
     * @return array{ok: bool, message: string, verification?: array<string, mixed>}
     */
    public function verifyPortalBundleOnRouter(Router $router): array
    {
        try {
            $v = $this->mikrotik->verifyHotspotBundle($router->fresh());
            $router->forceFill(['last_portal_check_at' => now()])->save();
            $this->log('Portal bundle verified on router from admin', $router, ['ok' => $v['ok'] ?? false]);

            return [
                'ok' => true,
                'message' => ($v['ok'] ?? false) ? __('Portal bundle files look correct on router.') : implode('; ', $v['issues'] ?? []),
                'verification' => $v,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function rotateApiCredentials(Router $router): array
    {
        try {
            $this->credentials->rotateZtpPassword($router->fresh());
            $this->log('ZTP API credentials rotated from admin', $router, [
                'version' => $router->fresh()->api_credential_version,
            ]);

            return ['ok' => true, 'message' => __('New ZTP password issued — regenerate full setup script for the customer.')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function markScriptReissued(Router $router): array
    {
        $fresh = $router->fresh();
        $this->onboarding->recordScriptGenerated($fresh, [['message' => 'Admin marked script as reissued — customer should fetch a fresh script.']], null);
        $this->log('Script marked reissued from admin', $router, []);

        return ['ok' => true, 'message' => __('Onboarding set to script generated; ask customer to download again.')];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function markForReOnboarding(Router $router): array
    {
        $router->forceFill([
            'onboarding_status' => RouterOnboarding::CLAIMED,
            'ready_at' => null,
            'health_snapshot' => null,
            'health_evaluated_at' => null,
        ])->save();

        $this->log('Router marked for re-onboarding from admin', $router, []);

        return ['ok' => true, 'message' => __('Router reset to claimed — customer should re-run setup flow.')];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(string $description, Router $router, array $properties): void
    {
        $user = Auth::user();
        ActivityLog::record(
            $description,
            $router,
            $user instanceof User ? $user : null,
            $properties
        );
    }
}
