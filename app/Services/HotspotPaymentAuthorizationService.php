<?php

namespace App\Services;

use App\Jobs\AuthorizeHotspotPaymentJob;
use App\Models\ActivityLog;
use App\Models\HotspotPayment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HotspotPaymentAuthorizationService
{
    public function __construct(
        private readonly MikrotikApiService $mikrotik,
        private readonly HotspotPaymentAuthorizationContextRecorder $failureRecorder,
    ) {}

    /**
     * Apply MikroTik hotspot access for a paid session. Returns true when the
     * router accepted the hotspot user/profile configuration.
     */
    public function authorizePayment(HotspotPayment $payment): bool
    {
        $payment->loadMissing(['router', 'plan']);
        $router = $payment->router;
        $plan = $payment->plan;

        if (! $router || ! $plan) {
            Log::error('HotspotPaymentAuthorization: missing router or plan', [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
            ]);
            $this->failureRecorder->recordAuthorizeFailure(
                $payment->fresh(),
                'Missing router or plan for authorization.',
                ['source' => 'authorize_payment']
            );

            return false;
        }

        $profileName = 'sky-'.Str::slug($plan->name, '-');
        $rateLimit = $plan->mikrotikRateLimit();
        $expiresAt = now()->addMinutes($plan->duration_minutes);

        $hadPriorFailure = $payment->fresh()->first_authorize_failure_at !== null;

        try {
            $this->mikrotik->connectZtp($router);
            $this->mikrotik->authorizeHotspotMac(
                $payment->client_mac,
                $profileName,
                $plan->duration_minutes,
                $plan->data_quota_mb,
                $rateLimit
            );
            $this->mikrotik->disconnect();

            $successAttrs = [
                'status' => 'authorized',
                'authorized_at' => now(),
                'expires_at' => $expiresAt,
                'last_authorize_error' => null,
            ];

            if ($hadPriorFailure) {
                $payment->refresh();
                $successAttrs['recovered_after_failure_at'] = now();
                $successAttrs['failed_authorize_attempts_before_success'] = max(0, (int) $payment->authorize_attempts - 1);
                if ($payment->first_authorize_failure_at) {
                    $successAttrs['seconds_to_recover_from_first_failure'] = (int) round(
                        now()->diffInSeconds($payment->first_authorize_failure_at)
                    );
                }

                ActivityLog::record(
                    'Hotspot payment authorized after prior failure',
                    $payment,
                    Auth::user() instanceof User ? Auth::user() : null,
                    [
                        'reference' => $payment->reference,
                        'failed_attempts_before_success' => $successAttrs['failed_authorize_attempts_before_success'],
                    ]
                );
            }

            $payment->update($successAttrs);

            $router->forceFill([
                'last_api_success_at' => now(),
                'last_api_error' => null,
            ])->save();

            Log::info('HotspotPaymentAuthorization: authorized on router', [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'mac' => $payment->client_mac,
                'router_id' => $router->id,
                'plan' => $plan->name,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->mikrotik->disconnect();

            $msg = $e->getMessage();

            $router->forceFill([
                'last_api_error' => $msg,
            ])->save();

            $this->failureRecorder->recordAuthorizeFailure(
                $payment->fresh(),
                $msg,
                ['source' => 'authorize_payment', 'exception' => $e::class]
            );

            Log::warning('HotspotPaymentAuthorization: router authorize failed', [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'router_id' => $router->id,
                'error' => $msg,
            ]);

            return false;
        }
    }

    /**
     * Queue durable authorization when the provider has confirmed payment.
     * Safe to call multiple times; duplicate unique jobs are collapsed.
     */
    public function dispatchAuthorization(HotspotPayment $payment): void
    {
        $payment->refresh();

        if ($payment->status === 'authorized' || $payment->status !== 'success') {
            return;
        }

        AuthorizeHotspotPaymentJob::dispatch($payment->id)->afterCommit();
    }

    /**
     * Reset authorization attempts and queue a fresh job (admin/support recovery).
     *
     * @throws \InvalidArgumentException
     */
    public function adminRetryAuthorization(HotspotPayment $payment): void
    {
        $payment->refresh();

        if ($payment->status !== 'success') {
            throw new \InvalidArgumentException('Only provider-confirmed payments (status success) can retry MikroTik authorization.');
        }

        $payment->forceFill([
            'authorize_attempts' => 0,
            'last_authorize_error' => null,
            'authorize_retry_exhausted_at' => null,
            'admin_authorize_retry_count' => (int) $payment->admin_authorize_retry_count + 1,
            'last_admin_authorize_retry_at' => now(),
        ])->save();

        ActivityLog::record(
            'Hotspot payment admin authorize retry',
            $payment,
            Auth::user() instanceof User ? Auth::user() : null,
            [
                'reference' => $payment->reference,
                'router_id' => $payment->router_id,
                'admin_retry_count' => $payment->admin_authorize_retry_count,
            ]
        );

        $this->dispatchAuthorization($payment->fresh());
    }
}
