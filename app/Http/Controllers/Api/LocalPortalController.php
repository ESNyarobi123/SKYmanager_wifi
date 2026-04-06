<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerBillingPlan;
use App\Models\CustomerVoucher;
use App\Models\HotspotPayment;
use App\Models\Router;
use App\Services\HotspotPaymentAuthorizationService;
use App\Services\MikrotikApiService;
use App\Services\PaymentGatewayService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Local Captive Portal API
 *
 * Public endpoints used from MikroTik-served login.html inside the walled garden.
 * Security: router ownership, plan ownership, optional per-router portal token (after
 * HTML regeneration), rate limits (routes), idempotent payment finalization.
 */
class LocalPortalController extends Controller
{
    public function packages(Request $request): JsonResponse
    {
        $router = $this->resolveRouter((string) $request->query('router_id', ''));

        if (! $router) {
            return $this->routerNotFound();
        }

        $plans = CustomerBillingPlan::where('customer_id', $router->user_id)
            ->where('is_active', true)
            ->orderBy('price')
            ->get()
            ->map(fn (CustomerBillingPlan $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'duration_label' => $p->durationLabel(),
                'speed_label' => $p->speedLabel(),
                'description' => $p->description,
            ]);

        return response()->json([
            'router_name' => $router->hotspot_ssid ?: $router->name,
            'portal_bundle_version' => $router->portal_bundle_version,
            'plans' => $plans,
        ]);
    }

    public function startSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'router_id' => ['required', 'string', 'max:32'],
            'mac' => ['required', 'string', 'max:32'],
            'ip' => ['nullable', 'string', 'max:45'],
        ]);

        $router = $this->resolveRouter($data['router_id']);

        if (! $router) {
            return $this->routerNotFound();
        }

        Log::info('LocalPortal: session started', [
            'router_id' => $router->id,
            'router' => $router->name,
            'mac' => $data['mac'],
            'ip' => $data['ip'] ?? null,
        ]);

        return response()->json([
            'session_id' => Str::random(32),
            'router_name' => $router->hotspot_ssid ?: $router->name,
            'router_id' => $router->id,
            'started_at' => now()->toIso8601String(),
        ]);
    }

    public function initiatePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'router_id' => ['required', 'string', 'max:32'],
            'plan_id' => ['required', 'string', 'max:32'],
            'phone' => ['required', 'string', 'min:9', 'max:15'],
            'mac' => ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/'],
            'ip' => ['required', 'ip'],
        ]);

        $router = $this->resolveRouter($data['router_id']);

        if (! $router) {
            return response()->json([
                'error' => 'Router not found or not yet claimed.',
                'code' => 'router_not_found',
            ], 404);
        }

        if ($response = $this->rejectUnlessPortalTokenMatches($request, $router)) {
            return $response;
        }

        $plan = $this->resolvePlan($data['plan_id'], (string) $router->user_id);

        if (! $plan) {
            return response()->json([
                'error' => 'Plan not found or inactive for this hotspot.',
                'code' => 'plan_not_found',
            ], 404);
        }

        $mac = strtoupper($data['mac']);

        $existing = HotspotPayment::where('client_mac', $mac)
            ->where('plan_id', $plan->id)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->first();

        if ($existing) {
            return response()->json([
                'reference' => $existing->reference,
                'status' => 'pending',
                'code' => 'payment_already_pending',
                'message' => 'A payment is already pending. Check your phone for the USSD prompt.',
            ]);
        }

        $reference = 'HP-'.strtoupper(Str::random(12));

        $gatewayService = PaymentGatewayService::forRouter($router);

        $payment = HotspotPayment::create([
            'router_id' => $router->id,
            'plan_id' => $plan->id,
            'customer_payment_gateway_id' => $gatewayService->activeGatewayId(),
            'client_mac' => $mac,
            'client_ip' => $data['ip'],
            'phone' => $data['phone'],
            'amount' => $plan->price,
            'reference' => $reference,
            'status' => 'pending',
        ]);

        try {
            $result = $gatewayService->initiatePayment($data['phone'], (float) $plan->price, $reference);

            $payment->update(['transaction_id' => $result['transactionId']]);

            Log::info('LocalPortal: payment initiated', [
                'reference' => $reference,
                'router_id' => $router->id,
                'plan' => $plan->name,
            ]);

            return response()->json([
                'reference' => $reference,
                'status' => 'pending',
                'code' => 'initiated',
                'message' => 'USSD push sent. Enter your PIN on your phone.',
            ]);
        } catch (\Throwable $e) {
            $payment->update(['status' => 'failed']);

            Log::error('LocalPortal: payment initiation failed', [
                'reference' => $reference,
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
                'code' => 'provider_initiate_failed',
            ], 502);
        }
    }

    public function paymentStatus(string $reference): JsonResponse
    {
        if (! preg_match('/^HP-[A-Z0-9]{12}$/', $reference)) {
            return response()->json([
                'error' => 'Invalid payment reference format.',
                'code' => 'invalid_reference',
            ], 422);
        }

        $payment = HotspotPayment::where('reference', $reference)->first();

        if (! $payment) {
            return response()->json([
                'error' => 'Payment session not found.',
                'code' => 'payment_not_found',
            ], 404);
        }

        if ($payment->status === 'authorized') {
            return response()->json([
                'status' => 'authorized',
                'authorization' => 'complete',
            ]);
        }

        if ($payment->status === 'failed') {
            return response()->json([
                'status' => 'failed',
                'authorization' => 'failed',
                'message' => 'Payment did not complete. Please try again.',
            ]);
        }

        if ($payment->transaction_id && in_array($payment->status, ['pending', 'success'], true)) {
            try {
                $result = PaymentGatewayService::forHotspotPayment($payment)
                    ->verifyTransaction($payment->transaction_id);

                if ($result['status'] === 'success') {
                    HotspotPayment::markProviderConfirmedByReference($reference, $payment->transaction_id);
                    $payment->refresh();
                } elseif ($result['status'] === 'failed') {
                    DB::transaction(function () use ($reference) {
                        /** @var HotspotPayment|null $locked */
                        $locked = HotspotPayment::where('reference', $reference)->lockForUpdate()->first();
                        if ($locked && $locked->status === 'pending') {
                            $locked->update(['status' => 'failed']);
                        }
                    });
                    $payment->refresh();
                }
            } catch (\Throwable $e) {
                Log::warning('LocalPortal: status poll verify failed', [
                    'reference' => $reference,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $fresh = $payment->fresh();

        if ($fresh->status === 'success') {
            app(HotspotPaymentAuthorizationService::class)->dispatchAuthorization($fresh);

            return response()->json([
                'status' => 'success',
                'authorization' => 'pending',
                'message' => 'Payment confirmed. Activating internet access on the router…',
                'last_authorize_error' => $fresh->last_authorize_error,
            ]);
        }

        return response()->json([
            'status' => $fresh->status,
            'authorization' => $fresh->status === 'authorized' ? 'complete' : 'pending',
            'last_authorize_error' => $fresh->last_authorize_error,
        ]);
    }

    public function paymentCallback(Request $request): JsonResponse
    {
        if (! $this->isValidClickPesaWebhook($request)) {
            Log::warning('LocalPortal: ClickPesa callback rejected (signature)', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['received' => false, 'code' => 'invalid_signature'], 401);
        }

        $payload = $request->all();

        Log::info('LocalPortal: ClickPesa callback received', ['payload' => $payload]);

        $reference = $payload['orderReference'] ?? $payload['reference'] ?? null;

        if (! $reference || ! is_string($reference)) {
            return response()->json(['received' => true]);
        }

        $clickpesaStatus = strtoupper((string) ($payload['status'] ?? ''));

        if (in_array($clickpesaStatus, ['SUCCESS', 'SETTLED'], true)) {
            $tid = isset($payload['id']) ? (string) $payload['id'] : null;
            HotspotPayment::markProviderConfirmedByReference($reference, $tid);
        } elseif ($clickpesaStatus === 'FAILED') {
            DB::transaction(function () use ($reference) {
                /** @var HotspotPayment|null $locked */
                $locked = HotspotPayment::where('reference', $reference)->lockForUpdate()->first();
                if ($locked && $locked->status === 'pending') {
                    $locked->update(['status' => 'failed']);
                }
            });

            Log::info('LocalPortal: payment failed via callback', ['reference' => $reference]);
        }

        return response()->json(['received' => true]);
    }

    public function authorizeUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'regex:/^HP-[A-Z0-9]{12}$/'],
        ]);

        $payment = HotspotPayment::where('reference', $data['reference'])->first();

        if (! $payment) {
            return response()->json([
                'error' => 'Payment session not found.',
                'code' => 'payment_not_found',
            ], 404);
        }

        $payment->loadMissing('router');

        if ($response = $this->rejectUnlessPortalTokenMatches($request, $payment->router)) {
            return $response;
        }

        if ($payment->status === 'authorized') {
            return response()->json([
                'status' => 'authorized',
                'authorization' => 'complete',
                'message' => 'Already authorized.',
            ]);
        }

        if ($payment->status === 'pending') {
            return response()->json([
                'status' => 'pending',
                'authorization' => 'pending',
                'message' => 'Payment is still pending. Complete the USSD prompt first.',
            ], 202);
        }

        if ($payment->status === 'failed') {
            return response()->json([
                'status' => 'failed',
                'authorization' => 'failed',
                'message' => 'Payment failed. Please initiate a new payment.',
            ], 402);
        }

        if ($payment->status !== 'success') {
            return response()->json([
                'status' => $payment->status,
                'code' => 'unexpected_payment_state',
            ], 409);
        }

        $authorization = app(HotspotPaymentAuthorizationService::class);

        if ($authorization->authorizePayment($payment->fresh())) {
            return response()->json([
                'status' => 'authorized',
                'authorization' => 'complete',
            ]);
        }

        $authorization->dispatchAuthorization($payment->fresh());

        return response()->json([
            'status' => 'success',
            'authorization' => 'pending',
            'message' => 'Router is applying access. This may take a minute if the link is busy.',
            'last_authorize_error' => $payment->fresh()->last_authorize_error,
        ], 202);
    }

    public function redeemVoucher(Request $request): JsonResponse
    {
        $data = $request->validate([
            'router_id' => ['required', 'string', 'max:32'],
            'code' => ['required', 'string', 'max:40'],
            'mac' => ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/'],
            'ip' => ['nullable', 'string', 'max:45'],
        ]);

        $router = $this->resolveRouter($data['router_id']);

        if (! $router) {
            return $this->routerNotFound();
        }

        if ($response = $this->rejectUnlessPortalTokenMatches($request, $router)) {
            return $response;
        }

        $mac = strtoupper($data['mac']);

        try {
            $voucher = CustomerVoucher::redeemForRouter($data['code'], $mac, $router);
        } catch (ModelNotFoundException) {
            return response()->json([
                'error' => 'Voucher code not found.',
                'code' => 'voucher_not_found',
            ], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => 'voucher_invalid',
            ], 422);
        }

        $plan = $voucher->plan;
        $profileName = 'sky-'.Str::slug($plan->name, '-');
        $rateLimit = $plan->mikrotikRateLimit();

        try {
            $mikrotik = app(MikrotikApiService::class);
            $mikrotik->connectZtp($router);
            $mikrotik->authorizeHotspotMac(
                $mac,
                $profileName,
                $plan->duration_minutes,
                $plan->data_quota_mb,
                $rateLimit !== '0/0' ? $rateLimit : null
            );
            $mikrotik->disconnect();

            $router->forceFill([
                'last_api_success_at' => now(),
                'last_api_error' => null,
            ])->save();

            Log::info('LocalPortal: customer voucher redeemed', [
                'router_id' => $router->id,
                'mac' => $mac,
                'plan' => $plan->name,
            ]);
        } catch (\Throwable $e) {
            Log::error('LocalPortal: MikroTik failed after customer voucher redeem', [
                'router_id' => $router->id,
                'mac' => $mac,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'pending',
                'code' => 'router_authorize_pending',
                'plan_name' => $plan->name,
                'duration_minutes' => $plan->duration_minutes,
                'message' => 'Voucher accepted. Access will activate shortly — please stay on Wi‑Fi.',
                'error_detail' => $e->getMessage(),
            ], 202);
        }

        return response()->json([
            'status' => 'authorized',
            'plan_name' => $plan->name,
            'duration_minutes' => $plan->duration_minutes,
            'message' => 'Voucher redeemed! You are now connected to the internet.',
        ]);
    }

    private function resolveRouter(string $routerId): ?Router
    {
        if ($routerId === '') {
            return null;
        }

        $router = Router::find($routerId);

        return ($router && $router->user_id) ? $router : null;
    }

    private function resolvePlan(string $planId, string $customerId): ?CustomerBillingPlan
    {
        return CustomerBillingPlan::where('id', $planId)
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->first();
    }

    private function routerNotFound(): JsonResponse
    {
        return response()->json([
            'error' => 'Router not found or not yet claimed.',
            'code' => 'router_not_found',
        ], 404);
    }

    /**
     * When a router has a portal token (set on standalone HTML download), mutating
     * endpoints require header X-SKY-Portal-Token. Routers without a token stay
     * backward-compatible until the customer regenerates login.html.
     */
    private function rejectUnlessPortalTokenMatches(Request $request, ?Router $router): ?JsonResponse
    {
        if (! $router || ! $router->local_portal_token) {
            return null;
        }

        $sent = (string) $request->header('X-SKY-Portal-Token', '');

        if ($sent === '' || ! hash_equals($router->local_portal_token, $sent)) {
            return response()->json([
                'error' => 'Invalid or missing portal token. Refresh the hotspot bundle or regenerate the MikroTik script from My Routers / My Plans.',
                'code' => 'portal_token_mismatch',
            ], 403);
        }

        return null;
    }

    private function isValidClickPesaWebhook(Request $request): bool
    {
        $secret = (string) config('services.clickpesa.webhook_secret', '');

        if ($secret === '') {
            return true;
        }

        $headerName = (string) config('services.clickpesa.webhook_signature_header', 'X-ClickPesa-Signature');
        $signature = (string) $request->header($headerName, '');

        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
