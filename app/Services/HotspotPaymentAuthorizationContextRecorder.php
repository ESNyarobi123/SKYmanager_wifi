<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\HotspotPayment;
use App\Models\Router;
use App\Models\User;
use App\Support\RouterOperationalReadiness;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Persists router/health context when hotspot MikroTik authorization fails or exhausts retries.
 */
class HotspotPaymentAuthorizationContextRecorder
{
    public function __construct(
        private readonly RouterHealthService $health,
    ) {}

    /**
     * Record context after authorizePayment() returns false or throws (caller passes error message).
     *
     * @param  array<string, mixed>  $extraContext
     */
    public function recordAuthorizeFailure(
        HotspotPayment $payment,
        string $errorMessage,
        array $extraContext = []
    ): void {
        $payment->refresh();
        $router = $payment->router;

        $snapshot = $this->buildSnapshot($payment, $router, $errorMessage, $extraContext);

        $attrs = array_merge($snapshot['columns'], [
            'last_authorize_error' => $errorMessage,
        ]);

        if ($payment->first_authorize_failure_at === null) {
            $attrs['first_authorize_failure_at'] = now();
        }
        $attrs['last_authorize_failed_at'] = now();

        $payment->forceFill($attrs)->save();

        ActivityLog::record(
            'Hotspot payment authorize failed',
            $payment,
            Auth::user() instanceof User ? Auth::user() : null,
            [
                'reference' => $payment->reference,
                'attempt' => $payment->authorize_attempts,
                'error_code' => $attrs['last_authorize_error_code'] ?? null,
                'overall_at_failure' => $attrs['last_failure_overall_health'] ?? null,
            ]
        );
    }

    /**
     * Mark in-app authorize attempt budget exhausted (authorize_attempts >= max).
     */
    public function recordAttemptsExhausted(HotspotPayment $payment, string $reason = 'max_authorize_attempts'): void
    {
        $payment->refresh();

        if ($payment->authorize_retry_exhausted_at !== null) {
            return;
        }

        $router = $payment->router;

        $snapshot = $this->buildSnapshot($payment, $router, $payment->last_authorize_error ?? $reason, [
            'exhausted' => true,
            'reason' => $reason,
        ]);

        $attrs = $snapshot['columns'];
        $attrs['authorize_retry_exhausted_at'] = $payment->authorize_retry_exhausted_at ?? now();
        if ($payment->first_authorize_failure_at === null) {
            $attrs['first_authorize_failure_at'] = now();
        }
        $attrs['last_authorize_failed_at'] = now();

        $payment->forceFill($attrs)->save();

        ActivityLog::record(
            'Hotspot payment authorize retries exhausted',
            $payment,
            Auth::user() instanceof User ? Auth::user() : null,
            [
                'reference' => $payment->reference,
                'attempts' => $payment->authorize_attempts,
                'reason' => $reason,
            ]
        );

        Log::error('HotspotPayment: authorize attempts exhausted', [
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'reason' => $reason,
        ]);
    }

    /**
     * Queue worker exhausted job tries — record if not already exhausted by attempts counter.
     */
    public function recordQueueRetriesExhausted(HotspotPayment $payment, ?string $lastError = null): void
    {
        $payment->refresh();

        if ($payment->authorize_retry_exhausted_at !== null) {
            return;
        }

        $msg = $lastError ?? $payment->last_authorize_error ?? 'queue_retries_exhausted';

        $snapshot = $this->buildSnapshot($payment, $payment->router, $msg, [
            'exhausted' => true,
            'reason' => 'queue_job_failed',
        ]);

        $attrs = $snapshot['columns'];
        $attrs['authorize_retry_exhausted_at'] = $payment->authorize_retry_exhausted_at ?? now();
        if ($payment->first_authorize_failure_at === null) {
            $attrs['first_authorize_failure_at'] = now();
        }
        $attrs['last_authorize_failed_at'] = now();

        $payment->forceFill($attrs)->save();

        ActivityLog::record(
            'Hotspot payment authorize job exhausted (queue)',
            $payment,
            null,
            ['reference' => $payment->reference, 'error' => Str::limit($msg, 200)],
        );
    }

    /**
     * @return array{columns: array<string, mixed>, snapshot: array<string, mixed>}
     */
    private function buildSnapshot(HotspotPayment $payment, ?Router $router, string $errorMessage, array $extraContext): array
    {
        $code = $this->classifyError($errorMessage);

        if (! $router instanceof Router) {
            return [
                'columns' => [
                    'last_authorize_error_code' => 'missing_router',
                    'last_authorize_health_snapshot' => [
                        'error' => 'missing_router_or_plan',
                        'captured_at' => now()->toIso8601String(),
                        'extra' => $extraContext,
                    ],
                    'last_authorize_attempt_context' => array_merge([
                        'authorize_attempts' => $payment->authorize_attempts,
                        'payment_status' => $payment->status,
                    ], $extraContext),
                    'last_failure_router_online' => null,
                    'last_failure_overall_health' => 'error',
                    'last_failure_tunnel_level' => 'unknown',
                    'last_failure_api_level' => 'unknown',
                    'last_failure_portal_level' => 'unknown',
                    'router_ready_for_authorize_at_failure' => false,
                    'provider_confirmed_at_failure' => $payment->status === 'success' && $payment->provider_confirmed_at !== null,
                ],
                'snapshot' => [],
            ];
        }

        $report = $this->health->evaluate($router, false);
        $readiness = RouterOperationalReadiness::snapshot($router);

        $healthPayload = [
            'captured_at' => now()->toIso8601String(),
            'evaluate' => $report,
            'readiness' => $readiness,
            'router' => [
                'id' => $router->id,
                'name' => $router->name,
                'is_online' => $router->is_online,
                'onboarding_status' => $router->onboarding_status,
                'credential_mismatch_suspected' => $router->credential_mismatch_suspected,
                'vpn_connected' => $router->vpn_connected,
                'last_tunnel_ok' => $router->last_tunnel_ok,
            ],
            'extra' => $extraContext,
        ];

        return [
            'columns' => [
                'last_authorize_error_code' => $code,
                'last_authorize_health_snapshot' => $healthPayload,
                'last_authorize_attempt_context' => array_merge([
                    'authorize_attempts' => $payment->authorize_attempts,
                    'payment_status' => $payment->status,
                ], $extraContext),
                'last_failure_router_online' => (bool) $router->is_online,
                'last_failure_overall_health' => $report['overall'],
                'last_failure_tunnel_level' => $report['tunnel']['level'] ?? 'unknown',
                'last_failure_api_level' => $report['api']['level'] ?? 'unknown',
                'last_failure_portal_level' => $report['portal']['level'] ?? 'unknown',
                'router_ready_for_authorize_at_failure' => (bool) ($readiness['payment_authorize_likely'] ?? false),
                'provider_confirmed_at_failure' => $payment->status === 'success' && $payment->provider_confirmed_at !== null,
            ],
            'snapshot' => $healthPayload,
        ];
    }

    private function classifyError(string $message): string
    {
        $m = strtolower($message);

        if (str_contains($m, 'invalid user') || str_contains($m, 'invalid password') || str_contains($m, 'cannot log in')) {
            return 'api_auth';
        }
        if (str_contains($m, 'credential') && str_contains($m, 'mismatch')) {
            return 'api_cred_mismatch';
        }
        if (str_contains($m, 'timed out') || str_contains($m, 'connection refused') || str_contains($m, 'connection reset')) {
            return 'network';
        }
        if (str_contains($m, 'missing router')) {
            return 'missing_router';
        }

        return 'authorize_error';
    }
}
