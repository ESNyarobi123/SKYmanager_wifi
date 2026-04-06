<?php

namespace App\Jobs;

use App\Models\HotspotPayment;
use App\Services\HotspotPaymentAuthorizationContextRecorder;
use App\Services\HotspotPaymentAuthorizationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AuthorizeHotspotPaymentJob implements ShouldBeUnique
{
    use Queueable;

    public int $tries = 20;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 10, 15, 30, 45, 60, 90, 120, 180, 240, 300];

    public function __construct(public string $hotspotPaymentId) {}

    public function uniqueId(): string
    {
        return 'hotspot-authorize-'.$this->hotspotPaymentId;
    }

    public function handle(
        HotspotPaymentAuthorizationService $authorization,
        HotspotPaymentAuthorizationContextRecorder $failureRecorder,
    ): void {
        $payment = HotspotPayment::find($this->hotspotPaymentId);

        if (! $payment instanceof HotspotPayment) {
            return;
        }

        $payment->refresh();

        if ($payment->status === 'authorized') {
            return;
        }

        if (in_array($payment->status, ['failed', 'pending'], true)) {
            Log::info('HotspotAuthorizeJob: skipped — payment not in success state', [
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]);

            return;
        }

        if ($payment->status !== 'success') {
            return;
        }

        $max = (int) config('skymanager.hotspot_authorize_max_attempts', 30);
        if ($payment->authorize_attempts >= $max) {
            Log::error('HotspotAuthorizeJob: max authorization attempts exceeded', [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'attempts' => $payment->authorize_attempts,
            ]);
            $failureRecorder->recordAttemptsExhausted($payment->fresh());

            return;
        }

        $payment->increment('authorize_attempts');
        $payment->refresh();

        if ($payment->authorization_job_dispatched_at === null) {
            $payment->update(['authorization_job_dispatched_at' => now()]);
            $payment->refresh();
        }

        $ok = $authorization->authorizePayment($payment);

        if ($ok) {
            return;
        }

        throw new RuntimeException('MikroTik authorization pending — router unreachable or API error.');
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('HotspotAuthorizeJob: exhausted retries', [
            'payment_id' => $this->hotspotPaymentId,
            'error' => $exception?->getMessage(),
        ]);

        $payment = HotspotPayment::find($this->hotspotPaymentId);
        if (! $payment instanceof HotspotPayment) {
            return;
        }

        app(HotspotPaymentAuthorizationContextRecorder::class)
            ->recordQueueRetriesExhausted($payment->fresh(), $exception?->getMessage());
    }
}
