<?php

namespace App\Services;

use App\Models\CustomerBillingPlan;
use App\Models\CustomerPaymentGateway;
use App\Models\Router;
use App\Models\User;

class StandalonePortalHtmlService
{
    /**
     * Generate a completely self-contained HTML portal (legacy single-file) for the router and
     * its owning customer.
     *
     * The returned string can be saved as hotspot/login.html on the router when using
     * this legacy path; production setups should use the hotspot bundle + setup script instead. It contains:
     *   - All active billing plans for THIS customer (embedded as JSON — no API call needed)
     *   - Customer branding: company name, WiFi SSID
     *   - Payment gateway status: whether customer has configured ClickPesa credentials
     *   - Complete vanilla JS payment flow (no external CDN dependencies)
     *   - MikroTik macro placeholders: $(mac), $(ip), $(link-login-only), $(link-orig)
     *   - VPS API calls (walled-garden): session/start, payment/initiate,
     *     payment/status, mikrotik/authorize
     *
     * NOTE: ClickPesa consumer_key/consumer_secret are NEVER embedded in the HTML.
     * They are used server-side by the VPS API which the JS calls. The HTML only
     * embeds a label indicating which payment networks are accepted.
     */
    /**
     * @return array<string, mixed>
     */
    public function viewDataForRouter(Router $router, User $customer): array
    {
        $plans = CustomerBillingPlan::where('customer_id', $customer->id)
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
            ])
            ->values();

        $gateway = CustomerPaymentGateway::where('customer_id', $customer->id)
            ->where('gateway', 'clickpesa')
            ->where('is_active', true)
            ->first();

        $hasCustomGateway = $gateway instanceof CustomerPaymentGateway && $gateway->isConfigured();

        return [
            'vpsUrl' => rtrim(config('app.url'), '/'),
            'routerId' => $router->id,
            'wifiName' => $router->hotspot_ssid ?: $router->name ?: 'WiFi',
            'companyName' => $customer->company_name ?: $customer->name,
            'hasCustomGateway' => $hasCustomGateway,
            'plansJson' => $plans->toJson(JSON_UNESCAPED_UNICODE),
            'generatedAt' => now()->toDateTimeString(),
            'portalToken' => $router->local_portal_token ?? '',
            'portalBuild' => $router->portal_bundle_version ?: (string) config('skymanager.portal_bundle_version'),
            'bundleMode' => false,
        ];
    }

    public function generateForRouter(Router $router, User $customer): string
    {
        return view('hotspot.standalone-login', $this->viewDataForRouter($router, $customer))->render();
    }

    /**
     * Backward-compatible wrapper — resolves the customer from the router's owner.
     * Prefer generateForRouter() when the authenticated user is already available.
     */
    public function generate(Router $router): string
    {
        $customer = $router->user ?? new User;

        return $this->generateForRouter($router, $customer);
    }
}
