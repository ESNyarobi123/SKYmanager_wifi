<?php

namespace App\Services;

use App\Models\BillingPlan;
use App\Models\CustomerVoucher;
use App\Models\HotspotPayment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WifiUser;
use App\Support\RouterOnboarding;
use Illuminate\Support\Collection;

/**
 * Reusable KPI blocks for customer portal, reseller/admin dashboard, and reporting hints.
 */
class BusinessKpiService
{
    public function __construct(
        private readonly PaymentIncidentSummaryService $incidents,
    ) {}

    /**
     * Customer portal (WiFi business owner) — own routers and billing only.
     *
     * @return array<string, mixed>
     */
    public function customerOperationsSummary(User $customer): array
    {
        $routerIds = $customer->routers()->pluck('id');

        $routers = Router::query()->whereIn('id', $routerIds);
        $gatewayConfigured = $customer->paymentGateways()->where('is_active', true)->exists();

        $weekStart = now()->subDays(7)->startOfDay();
        $hpWeek = HotspotPayment::query()
            ->whereIn('router_id', $routerIds)
            ->where('created_at', '>=', $weekStart);

        $hpAttemptedAuth = (clone $hpWeek)->whereIn('status', ['success', 'authorized', 'failed'])
            ->where(function ($q) {
                $q->whereNotNull('authorized_at')
                    ->orWhereNotNull('last_authorize_failed_at')
                    ->orWhere('authorize_attempts', '>', 0);
            });

        $authorizedCount = (clone $hpAttemptedAuth)->where('status', 'authorized')->count();
        $failedAuthCount = (clone $hpWeek)
            ->where('status', 'success')
            ->whereNotNull('last_authorize_failed_at')
            ->whereNull('authorized_at')
            ->count();
        $authAttemptsDenom = $authorizedCount + $failedAuthCount;
        $authRatePct = $authAttemptsDenom > 0
            ? round(100 * $authorizedCount / $authAttemptsDenom, 1)
            : 100.0;

        $vouchersUnused = CustomerVoucher::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'unused')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        $vouchersUsed = CustomerVoucher::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'used')
            ->count();

        $invoicesPending = $customer->invoices()->where('status', 'issued')->count();
        $lastInvoice = $customer->invoices()->latest('issued_at')->first();

        $legacyRouters = (clone $routers)->where(function ($q) {
            $q->whereNull('bundle_deployment_mode')
                ->orWhere('bundle_deployment_mode', '!=', 'bundle');
        })->count();

        $trustMessages = $this->buildCustomerTrustMessages(
            $gatewayConfigured,
            $routers,
            $legacyRouters,
            $authRatePct,
            $authAttemptsDenom > 0,
            $invoicesPending,
            $lastInvoice,
            $vouchersUnused
        );

        return [
            'routers_total' => $routerIds->count(),
            'routers_online' => (clone $routers)->where('is_online', true)->count(),
            'routers_ready' => (clone $routers)->where('onboarding_status', RouterOnboarding::READY)->count(),
            'routers_degraded' => (clone $routers)->where('onboarding_status', RouterOnboarding::DEGRADED)->count(),
            'routers_offline_onboarding' => (clone $routers)->where('onboarding_status', RouterOnboarding::OFFLINE)->count(),
            'routers_legacy_bundle' => $legacyRouters,
            'payment_gateway_configured' => $gatewayConfigured,
            'billing_plans_active' => $customer->billingPlans()->where('is_active', true)->count(),
            'vouchers_unused' => $vouchersUnused,
            'vouchers_used' => $vouchersUsed,
            'hotspot_payments_week' => (clone $hpWeek)->count(),
            'hotspot_auth_success_rate_week_pct' => $authRatePct,
            'invoices_pending' => $invoicesPending,
            'last_invoice_issued_at' => $lastInvoice?->issued_at,
            'incidents' => $this->incidents->summarize($customer->id),
            'trust_messages' => $trustMessages,
        ];
    }

    /**
     * Main app dashboard (admin sees platform; reseller sees own portfolio).
     *
     * @return array<string, int|float|string>
     */
    public function platformOrResellerDashboardStats(User $actor): array
    {
        if (TenantReportingScope::isPlatformAdmin($actor)) {
            return [
                'active_sessions' => Subscription::query()
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->count(),
                'online_routers' => Router::query()->where('is_online', true)->count(),
                'total_routers' => Router::query()->count(),
                'revenue_today' => (float) Payment::query()->where('status', 'success')
                    ->whereDate('created_at', today())
                    ->sum('amount'),
                'revenue_month' => (float) Payment::query()->where('status', 'success')
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('amount'),
                'active_plans' => BillingPlan::query()->where('is_active', true)->count(),
                'total_users' => WifiUser::query()->count(),
            ];
        }

        $routerIds = $actor->routers()->pluck('id');
        if ($routerIds->isEmpty()) {
            return [
                'active_sessions' => 0,
                'online_routers' => 0,
                'total_routers' => 0,
                'revenue_today' => 0.0,
                'revenue_month' => 0.0,
                'active_plans' => $actor->billingPlans()->where('is_active', true)->count(),
                'total_users' => 0,
            ];
        }

        $subIds = Subscription::query()->whereIn('router_id', $routerIds)->pluck('id');

        return [
            'active_sessions' => Subscription::query()
                ->whereIn('router_id', $routerIds)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->count(),
            'online_routers' => Router::query()->whereIn('id', $routerIds)->where('is_online', true)->count(),
            'total_routers' => $routerIds->count(),
            'revenue_today' => (float) Payment::query()->whereIn('subscription_id', $subIds)
                ->where('status', 'success')
                ->whereDate('created_at', today())
                ->sum('amount'),
            'revenue_month' => (float) Payment::query()->whereIn('subscription_id', $subIds)
                ->where('status', 'success')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount'),
            'active_plans' => $actor->billingPlans()->where('is_active', true)->count(),
            'total_users' => WifiUser::query()
                ->whereHas('subscriptions', fn ($q) => $q->whereIn('router_id', $routerIds))
                ->distinct()
                ->count('id'),
        ];
    }

    /**
     * Recent subscription payments for dashboard table (platform or scoped).
     *
     * @return Collection<int, Payment>
     */
    public function recentSubscriptionPayments(User $actor, int $limit = 8): Collection
    {
        $q = Payment::query()->with(['subscription.wifiUser', 'subscription.plan', 'subscription.router']);

        if (! TenantReportingScope::isPlatformAdmin($actor)) {
            $routerIds = $actor->routers()->pluck('id');
            if ($routerIds->isEmpty()) {
                return collect();
            }
            $q->whereHas('subscription', fn ($sq) => $sq->whereIn('router_id', $routerIds));
        }

        return $q->latest()->limit($limit)->get();
    }

    /**
     * Incident summary for dashboard widget — scoped for reseller.
     *
     * @return array<string, int>
     */
    public function operationsIncidentSummaryFor(User $actor): array
    {
        if (TenantReportingScope::isPlatformAdmin($actor)) {
            return $this->incidents->summarize();
        }

        return $this->incidents->summarize($actor->id);
    }

    /**
     * @return list<string>
     */
    private function buildCustomerTrustMessages(
        bool $gatewayConfigured,
        $routersQuery,
        int $legacyRouters,
        float $authRatePct,
        bool $hadHotspotAuthActivity,
        int $invoicesPending,
        ?Invoice $lastInvoice,
        int $vouchersUnused
    ): array {
        $messages = [];

        if (! $gatewayConfigured) {
            $messages[] = __('Your payment gateway is not configured or enabled — hotspot payments may not complete until you add one in Payment settings.');
        }

        $total = (clone $routersQuery)->count();
        $offline = (clone $routersQuery)->where('is_online', false)->count();
        if ($total > 0 && $offline > 0) {
            $messages[] = __(':offline of your :total routers are offline right now.', ['offline' => $offline, 'total' => $total]);
        }

        if ($legacyRouters > 0) {
            $messages[] = __(':count router(s) still use legacy hotspot deployment — consider migrating to the bundle for easier support.', ['count' => $legacyRouters]);
        }

        if ($hadHotspotAuthActivity && $authRatePct < 90) {
            $messages[] = __('Hotspot authorization success rate this week: :pct% — check router health if customers report paid-but-no-internet.', ['pct' => $authRatePct]);
        }

        if ($invoicesPending > 0) {
            $messages[] = __('You have :count open invoice(s) awaiting payment.', ['count' => $invoicesPending]);
        }

        if ($lastInvoice?->issued_at) {
            $messages[] = __('Last invoice issued on :date.', ['date' => $lastInvoice->issued_at->toFormattedDateString()]);
        }

        if ($vouchersUnused < 5 && $vouchersUnused > 0) {
            $messages[] = __('Voucher stock is low (:count unused) — generate more if you sell codes.', ['count' => $vouchersUnused]);
        }

        return $messages;
    }
}
