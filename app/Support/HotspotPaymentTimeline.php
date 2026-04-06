<?php

namespace App\Support;

use App\Models\HotspotPayment;
use Illuminate\Support\Str;

/**
 * Build a support-oriented timeline from persisted payment fields (no giant JSON log).
 *
 * @return list<array{at: ?string, label: string, detail: string}>
 */
final class HotspotPaymentTimeline
{
    public static function build(HotspotPayment $payment): array
    {
        $rows = [];

        $rows[] = [
            'at' => $payment->created_at?->toIso8601String(),
            'label' => __('Payment initiated'),
            'detail' => $payment->reference,
        ];

        if ($payment->provider_confirmed_at) {
            $rows[] = [
                'at' => $payment->provider_confirmed_at->toIso8601String(),
                'label' => __('Provider confirmed'),
                'detail' => __('Status moved to success; MikroTik authorize can run.'),
            ];
        }

        if ($payment->authorization_job_dispatched_at) {
            $rows[] = [
                'at' => $payment->authorization_job_dispatched_at->toIso8601String(),
                'label' => __('Authorize job queued'),
                'detail' => '',
            ];
        }

        if ($payment->first_authorize_failure_at) {
            $rows[] = [
                'at' => $payment->first_authorize_failure_at->toIso8601String(),
                'label' => __('First authorize failure'),
                'detail' => __('Health snapshot captured for support.'),
            ];
        }

        if ($payment->last_authorize_failed_at) {
            $first = $payment->first_authorize_failure_at;
            $showLatestRow = $first === null
                || ! $payment->last_authorize_failed_at->equalTo($first);
            if ($showLatestRow) {
                $rows[] = [
                    'at' => $payment->last_authorize_failed_at->toIso8601String(),
                    'label' => __('Latest authorize failure'),
                    'detail' => Str::limit((string) $payment->last_authorize_error, 120),
                ];
            }
        }

        if ($payment->last_admin_authorize_retry_at) {
            $rows[] = [
                'at' => $payment->last_admin_authorize_retry_at->toIso8601String(),
                'label' => __('Admin retry'),
                'detail' => __('Count: :n', ['n' => $payment->admin_authorize_retry_count]),
            ];
        }

        if ($payment->authorize_retry_exhausted_at) {
            $rows[] = [
                'at' => $payment->authorize_retry_exhausted_at->toIso8601String(),
                'label' => __('Retries exhausted'),
                'detail' => __('No further automatic authorize attempts.'),
            ];
        }

        if ($payment->authorized_at) {
            $rows[] = [
                'at' => $payment->authorized_at->toIso8601String(),
                'label' => __('Authorized on router'),
                'detail' => $payment->recovered_after_failure_at
                    ? __('Access granted after earlier failure(s).')
                    : '',
            ];
        }

        usort($rows, function (array $a, array $b) {
            $ta = $a['at'] ?? '';
            $tb = $b['at'] ?? '';

            return strcmp((string) $ta, (string) $tb);
        });

        return $rows;
    }
}
