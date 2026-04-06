<?php

namespace App\Support;

use App\Models\HotspotPayment;
use App\Models\Router;

/**
 * Compare failure-time snapshot vs current router state for support copy.
 */
final class HotspotPaymentSupportHints
{
    /**
     * @param  array<string, mixed>|null  $liveHealthReport  Output of RouterHealthService::evaluate(..., false)
     * @return list<string>
     */
    public static function forPayment(HotspotPayment $payment, ?Router $router = null, ?array $liveHealthReport = null): array
    {
        $hints = [];
        $router = $router ?? $payment->router;

        if ($payment->status === 'pending' && ! $payment->provider_confirmed_at) {
            $hints[] = __('Provider has not confirmed payment yet — investigate payment channel before router authorize.');
        }

        if ($payment->status === 'success' && $payment->provider_confirmed_at && ! $payment->authorized_at) {
            $hints[] = __('Payment is provider-confirmed but MikroTik has not granted hotspot access yet.');
        }

        if (! $payment->last_authorize_failed_at) {
            if ($hints === []) {
                $hints[] = __('No authorize failure recorded on this payment yet.');
            }

            return $hints;
        }

        if ($payment->provider_confirmed_at_failure === false) {
            $hints[] = __('Failure happened before provider confirmation — likely payment-side or timing, not MikroTik authorize.');
        }

        if ($payment->provider_confirmed_at_failure === true && $payment->last_failure_router_online === false) {
            $hints[] = __('Payment provider had confirmed, but the router was offline during the authorize attempt.');
        }

        if ($payment->last_failure_router_online === false) {
            $hints[] = __('At last failure the router was marked offline — check tunnel/VPN and power.');
        }

        if ($payment->last_authorize_error_code === 'api_auth' || $payment->last_authorize_error_code === 'api_cred_mismatch') {
            $hints[] = __('Authorization failed while API credentials looked wrong or mismatched — rotate ZTP password and re-run setup script if needed.');
        }

        if (in_array($payment->last_failure_tunnel_level, ['error', 'unknown'], true)) {
            $hints[] = __('Tunnel was not healthy at failure — verify WireGuard handshake and VPS peer.');
        }

        if ($payment->last_failure_portal_level === 'error') {
            $hints[] = __('Portal/bundle dimension was error at failure — reconcile hotspot bundle vs router files.');
        }

        if ($payment->last_failure_portal_level === 'healthy'
            && in_array($payment->last_failure_tunnel_level, ['error', 'unknown'], true)) {
            $hints[] = __('Bundle/portal looked healthy at failure; tunnel or reachability was likely the issue.');
        }

        if ($router instanceof Router) {
            $nowOnline = (bool) $router->is_online;
            if ($payment->last_failure_router_online === false && $nowOnline) {
                $hints[] = __('Router was offline at failure but is online now — retry authorize may succeed.');
            }
            if ($payment->last_failure_router_online === true && ! $nowOnline) {
                $hints[] = __('Router was online at failure but is offline now — fix connectivity before retry.');
            }

            $readiness = RouterOperationalReadiness::snapshot($router);
            if ($payment->router_ready_for_authorize_at_failure === false && ($readiness['payment_authorize_likely'] ?? false)) {
                $hints[] = __('Router was not “authorize-ready” at failure; readiness looks better now — good candidate for retry.');
            }
        }

        if ($liveHealthReport !== null && $payment->last_failure_overall_health) {
            $liveOverall = (string) ($liveHealthReport['overall'] ?? '');
            if ($liveOverall === 'healthy' && $payment->last_failure_overall_health !== 'healthy') {
                $hints[] = __('Router health recovered after failure; safe to retry now if payment is still un-authorized.');
            }
            $liveApi = (string) ($liveHealthReport['api']['level'] ?? '');
            if (in_array($payment->last_failure_api_level, ['error', 'warning'], true)
                && $liveApi === 'healthy') {
                $hints[] = __('Failure happened with API issues; current evaluation shows API healthy.');
            }
        }

        if ($payment->recovered_after_failure_at && $payment->authorized_at) {
            $hints[] = __('Authorize eventually succeeded after earlier failure — recovery detected.');
        }

        if ($payment->authorize_retry_exhausted_at && ! $payment->authorized_at) {
            $hints[] = __('Automatic retries are exhausted — use admin retry or fix router then reset attempts.');
        }

        if ($hints === []) {
            $hints[] = __('Review failure snapshot and live health side by side below.');
        }

        return $hints;
    }
}
