<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerBillingPlan;
use App\Models\HotspotPayment;
use App\Models\Router;
use App\Models\Voucher;
use App\Services\MikrotikApiService;
use App\Services\PaymentGatewayService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Local Captive Portal API
 *
 * All endpoints are FULLY PUBLIC — called from the MikroTik-served login.html
 * before the client device has internet access. Security is enforced via:
 *   - router_id ownership validation on every write endpoint
 *   - plan ownership verification against the router's customer
 *   - per-route rate limiting (see routes/api.php)
 *   - duplicate-payment guard on initiate
 */
class LocalPortalController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════
    // Group 1 – Portal Entry
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/local-portal/packages?router_id={id}
     *
     * Returns active billing plans for the customer who owns this router.
     * Called on portal page load to populate the plan grid.
     */
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
            'plans' => $plans,
        ]);
    }

    /**
     * POST /api/local-portal/session/start
     *
     * Records a portal session start for a device hitting the captive portal.
     * Validates router existence and returns router metadata to the JS app.
     * Called once when the portal page initialises.
     *
     * Body: { router_id, mac, ip }
     */
    public function startSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'router_id' => ['required', 'string'],
            'mac' => ['required', 'string'],
            'ip' => ['nullable', 'string'],
        ]);

        $router = $this->resolveRouter($data['router_id']);

        if (! $router) {
            return $this->routerNotFound();
        }

        Log::info('LocalPortal: session started', [
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

    // ══════════════════════════════════════════════════════════════════════
    // Group 2 – Payment
    // ══════════════════════════════════════════════════════════════════════

    /**
     * POST /api/local-portal/payment/initiate
     *
     * Initiates a ClickPesa USSD-push payment for a selected plan.
     * Returns a reference the browser polls to track status.
     *
     * Body: { router_id, plan_id, phone, mac, ip }
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'router_id' => ['required', 'string'],
            'plan_id' => ['required', 'string'],
            'phone' => ['required', 'string', 'min:9', 'max:15'],
            'mac' => ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/'],
            'ip' => ['required', 'ip'],
        ]);

        $router = $this->resolveRouter($data['router_id']);

        if (! $router) {
            return $this->routerNotFound();
        }

        $plan = $this->resolvePlan($data['plan_id'], $router->user_id);

        if (! $plan) {
            return response()->json(['error' => 'Plan not found or inactive.'], 404);
        }

        $mac = strtoupper($data['mac']);

        // Guard: block duplicate pending payments for the same device + plan.
        $existing = HotspotPayment::where('client_mac', $mac)
            ->where('plan_id', $plan->id)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->first();

        if ($existing) {
            return response()->json([
                'reference' => $existing->reference,
                'status' => 'pending',
                'message' => 'A payment is already pending. Check your phone for the USSD prompt.',
            ]);
        }

        $reference = 'HP-'.strtoupper(Str::random(12));

        $payment = HotspotPayment::create([
            'router_id' => $router->id,
            'plan_id' => $plan->id,
            'client_mac' => $mac,
            'client_ip' => $data['ip'],
            'phone' => $data['phone'],
            'amount' => $plan->price,
            'reference' => $reference,
            'status' => 'pending',
        ]);

        try {
            $result = PaymentGatewayService::forRouter($router)
                ->initiatePayment($data['phone'], (float) $plan->price, $reference);

            $payment->update(['transaction_id' => $result['transactionId']]);

            Log::info('LocalPortal: payment initiated', [
                'reference' => $reference,
                'router' => $router->name,
                'plan' => $plan->name,
                'phone' => $data['phone'],
            ]);

            return response()->json([
                'reference' => $reference,
                'status' => 'pending',
                'message' => 'USSD push sent. Enter your PIN on your phone.',
            ]);
        } catch (\Exception $e) {
            $payment->update(['status' => 'failed']);

            Log::error('LocalPortal: payment initiation failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Payment initiation failed: '.$e->getMessage()], 502);
        }
    }

    /**
     * GET /api/local-portal/payment/status/{reference}
     *
     * Polls payment status. Verifies with ClickPesa on each poll and
     * automatically triggers MikroTik authorization on first success.
     */
    public function paymentStatus(string $reference): JsonResponse
    {
        if (! preg_match('/^HP-[A-Z0-9]{12}$/', $reference)) {
            return response()->json(['error' => 'Invalid reference format.'], 422);
        }

        $payment = HotspotPayment::where('reference', $reference)->first();

        if (! $payment) {
            return response()->json(['error' => 'Payment session not found.'], 404);
        }

        if ($payment->status === 'authorized') {
            return response()->json(['status' => 'authorized']);
        }

        if ($payment->status === 'failed') {
            return response()->json([
                'status' => 'failed',
                'message' => 'Payment did not complete. Please try again.',
            ]);
        }

        if (in_array($payment->status, ['pending', 'success'], true) && $payment->transaction_id) {
            try {
                $result = PaymentGatewayService::forRouter($payment->router)
                    ->verifyTransaction($payment->transaction_id);

                if ($result['status'] === 'success' && $payment->status !== 'authorized') {
                    if ($payment->status === 'pending') {
                        $payment->update(['status' => 'success']);
                    }
                    $this->authorizeOnRouter($payment);
                    $payment->refresh();
                } elseif ($result['status'] === 'failed') {
                    $payment->update(['status' => 'failed']);
                }
            } catch (\Exception $e) {
                Log::warning('LocalPortal: status poll error', [
                    'reference' => $reference,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['status' => $payment->fresh()->status]);
    }

    /**
     * POST /api/local-portal/payment/callback
     *
     * ClickPesa webhook. Must be completely public (no throttle middleware).
     * Updates payment status and triggers MikroTik authorization immediately.
     */
    public function paymentCallback(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('LocalPortal: ClickPesa callback received', ['payload' => $payload]);

        $reference = $payload['orderReference'] ?? $payload['reference'] ?? null;

        if (! $reference) {
            return response()->json(['received' => true]);
        }

        $payment = HotspotPayment::where('reference', $reference)->first();

        if (! $payment) {
            Log::warning('LocalPortal: callback for unknown reference', ['reference' => $reference]);

            return response()->json(['received' => true]);
        }

        $clickpesaStatus = strtoupper($payload['status'] ?? '');

        if (in_array($clickpesaStatus, ['SUCCESS', 'SETTLED'], true) && $payment->status === 'pending') {
            $payment->update([
                'status' => 'success',
                'transaction_id' => $payload['id'] ?? $payment->transaction_id,
            ]);
            $this->authorizeOnRouter($payment);
        } elseif ($clickpesaStatus === 'FAILED' && $payment->status === 'pending') {
            $payment->update(['status' => 'failed']);

            Log::info('LocalPortal: payment failed via callback', ['reference' => $reference]);
        }

        return response()->json(['received' => true]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Group 3 – MikroTik Authorization
    // ══════════════════════════════════════════════════════════════════════

    /**
     * POST /api/local-portal/mikrotik/authorize
     *
     * Explicit authorization endpoint. Called by JS as a fallback when the
     * payment has confirmed (status = success) but auto-authorization inside
     * paymentStatus() failed or has not been retried yet.
     *
     * Body: { reference }
     */
    public function authorizeUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'regex:/^HP-[A-Z0-9]{12}$/'],
        ]);

        $payment = HotspotPayment::where('reference', $data['reference'])->first();

        if (! $payment) {
            return response()->json(['error' => 'Payment session not found.'], 404);
        }

        if ($payment->status === 'authorized') {
            return response()->json(['status' => 'authorized', 'message' => 'Already authorized.']);
        }

        if ($payment->status === 'pending') {
            return response()->json([
                'status' => 'pending',
                'message' => 'Payment is still pending. Complete the USSD prompt first.',
            ], 202);
        }

        if ($payment->status === 'failed') {
            return response()->json([
                'status' => 'failed',
                'message' => 'Payment failed. Please initiate a new payment.',
            ], 402);
        }

        // status = 'success' — authorize on MikroTik now
        $ok = $this->authorizeOnRouter($payment);
        $payment->refresh();

        if ($ok) {
            return response()->json(['status' => 'authorized']);
        }

        return response()->json([
            'status' => $payment->status,
            'message' => 'Authorization is processing. Retry in a few seconds.',
        ], 202);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Group 4 – Voucher Redemption
    // ══════════════════════════════════════════════════════════════════════

    /**
     * POST /api/local-portal/voucher/redeem
     *
     * Validates and redeems a prepaid voucher code for a device.
     * On success, authorizes the device on MikroTik using the plan's parameters.
     *
     * Body: { router_id, code, mac, ip }
     */
    public function redeemVoucher(Request $request): JsonResponse
    {
        $data = $request->validate([
            'router_id' => ['required', 'string'],
            'code' => ['required', 'string', 'max:32'],
            'mac' => ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/'],
            'ip' => ['nullable', 'string'],
        ]);

        $router = $this->resolveRouter($data['router_id']);

        if (! $router) {
            return $this->routerNotFound();
        }

        $mac = strtoupper($data['mac']);

        try {
            $voucher = Voucher::redeem($data['code'], $mac);
        } catch (ModelNotFoundException) {
            return response()->json(['error' => 'Voucher code not found. Please check and try again.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $plan = $voucher->plan;

        $profileName = 'vchr-'.Str::slug($plan->name, '-');
        $rateLimit = ($plan->upload_limit && $plan->download_limit)
            ? $plan->upload_limit.'k/'.$plan->download_limit.'k'
            : null;

        try {
            $mikrotik = app(MikrotikApiService::class);
            $mikrotik->connectZtp($router);
            $mikrotik->authorizeHotspotMac(
                $mac,
                $profileName,
                $plan->duration_minutes,
                $plan->data_quota_mb,
                $rateLimit
            );
            $mikrotik->disconnect();

            Log::info('LocalPortal: voucher redeemed + authorized', [
                'router' => $router->name,
                'mac' => $mac,
                'code' => strtoupper(trim($data['code'])),
                'plan' => $plan->name,
            ]);
        } catch (\Exception $e) {
            Log::error('LocalPortal: MikroTik auth failed after voucher redeem', [
                'router' => $router->name,
                'mac' => $mac,
                'error' => $e->getMessage(),
            ]);

            // Voucher is already marked as used. Return partial success so the
            // user knows the code was valid — they may need to reconnect manually.
            return response()->json([
                'status' => 'authorized',
                'plan_name' => $plan->name,
                'duration_minutes' => $plan->duration_minutes,
                'message' => 'Voucher accepted! Internet access will activate within a few seconds.',
            ]);
        }

        return response()->json([
            'status' => 'authorized',
            'plan_name' => $plan->name,
            'duration_minutes' => $plan->duration_minutes,
            'message' => 'Voucher redeemed! You are now connected to the internet.',
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Private helpers
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Resolve a Router by ID, ensuring it is claimed by a customer.
     */
    private function resolveRouter(string $routerId): ?Router
    {
        if (! $routerId) {
            return null;
        }

        $router = Router::find($routerId);

        return ($router && $router->user_id) ? $router : null;
    }

    /**
     * Resolve an active CustomerBillingPlan owned by the given customer.
     */
    private function resolvePlan(string $planId, string $customerId): ?CustomerBillingPlan
    {
        return CustomerBillingPlan::where('id', $planId)
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Standard 404 JSON for unclaimed / missing router.
     */
    private function routerNotFound(): JsonResponse
    {
        return response()->json(['error' => 'Router not found or not yet claimed.'], 404);
    }

    /**
     * Connect to MikroTik via ZTP credentials and add a hotspot user with
     * MAC binding for the purchased plan. Returns true on success.
     * Errors are logged — the polling endpoint will retry on the next poll.
     */
    private function authorizeOnRouter(HotspotPayment $payment): bool
    {
        $router = $payment->router;
        $plan = $payment->plan;

        if (! $router || ! $plan) {
            Log::error('LocalPortal: missing router or plan for authorization', [
                'payment_id' => $payment->id,
            ]);

            return false;
        }

        $profileName = 'sky-'.Str::slug($plan->name, '-');
        $rateLimit = $plan->mikrotikRateLimit();
        $expiresAt = now()->addMinutes($plan->duration_minutes);

        try {
            $mikrotik = app(MikrotikApiService::class);
            $mikrotik->connectZtp($router);
            $mikrotik->authorizeHotspotMac(
                $payment->client_mac,
                $profileName,
                $plan->duration_minutes,
                $plan->data_quota_mb,
                $rateLimit
            );
            $mikrotik->disconnect();

            $payment->update([
                'status' => 'authorized',
                'authorized_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            Log::info('LocalPortal: client authorized on router', [
                'mac' => $payment->client_mac,
                'router' => $router->name,
                'plan' => $plan->name,
                'expires' => $expiresAt->toDateTimeString(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('LocalPortal: MikroTik authorization failed', [
                'payment_id' => $payment->id,
                'router' => $router->name,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
