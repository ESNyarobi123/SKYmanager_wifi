<?php

namespace App\Services;

use App\Models\CustomerBillingPlan;
use App\Models\CustomerVoucher;
use App\Models\HotspotPayment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Router;
use App\Models\User;
use App\Support\RouterOnboarding;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query/report layer for operations and finance screens — no ad hoc SQL in views.
 */
class OperationsReportService
{
    public function __construct(
        private readonly PaymentIncidentSummaryService $incidents,
    ) {}

    /**
     * @return list<string>|null
     */
    private function routerIds(User $actor): ?array
    {
        return TenantReportingScope::routerIdsFor($actor);
    }

    /**
     * Revenue rows (subscription payments).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function revenueReport(User $actor, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $q = Payment::query()
            ->with(['subscription.plan', 'subscription.router', 'subscription.wifiUser'])
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        $ids = $this->routerIds($actor);
        if ($ids !== null) {
            if ($ids === []) {
                return collect();
            }
            $q->whereHas('subscription', fn ($sq) => $sq->whereIn('router_id', $ids));
        }

        return $q->orderByDesc('created_at')
            ->get()
            ->map(fn (Payment $p) => [
                'id' => $p->id,
                'created_at' => $p->created_at?->toIso8601String(),
                'status' => $p->status,
                'amount' => (float) $p->amount,
                'provider' => $p->provider,
                'reference' => $p->reference,
                'customer_phone' => $p->subscription?->wifiUser?->phone_number ?? $p->subscription?->wifiUser?->mac_address,
                'plan_name' => $p->subscription?->plan?->name,
                'router_id' => $p->subscription?->router_id,
                'router_name' => $p->subscription?->router?->name,
            ]);
    }

    /**
     * Hotspot payment lifecycle report.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function hotspotPaymentReport(User $actor, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $q = HotspotPayment::query()
            ->with(['router', 'plan'])
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        TenantReportingScope::constrainHotspotPayments($q, $this->routerIds($actor));

        return $q->orderByDesc('created_at')
            ->get()
            ->map(fn (HotspotPayment $hp) => [
                'id' => $hp->id,
                'reference' => $hp->reference,
                'created_at' => $hp->created_at?->toIso8601String(),
                'status' => $hp->status,
                'amount' => (float) $hp->amount,
                'phone' => $hp->phone,
                'router_id' => $hp->router_id,
                'router_name' => $hp->router?->name,
                'plan_name' => $hp->plan?->name,
                'provider_confirmed_at' => $hp->provider_confirmed_at?->toIso8601String(),
                'authorized_at' => $hp->authorized_at?->toIso8601String(),
                'authorize_attempts' => $hp->authorize_attempts,
                'retry_exhausted_at' => $hp->authorize_retry_exhausted_at?->toIso8601String(),
                'recovered_after_failure_at' => $hp->recovered_after_failure_at?->toIso8601String(),
                'last_authorize_error' => $hp->last_authorize_error,
            ]);
    }

    /**
     * Router operations snapshot (current state, not historical time series).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function routerOperationsReport(User $actor): Collection
    {
        $q = Router::query()->with('user');

        TenantReportingScope::constrainRouters($q, $this->routerIds($actor));

        return $q->orderByDesc('updated_at')
            ->get()
            ->map(fn (Router $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'owner_id' => $r->user_id,
                'owner_name' => $r->user?->name,
                'is_online' => (bool) $r->is_online,
                'onboarding_status' => $r->onboarding_status,
                'credential_mismatch' => (bool) $r->credential_mismatch_suspected,
                'bundle_mode' => $r->bundle_deployment_mode,
                'portal_bundle_version' => $r->portal_bundle_version,
                'health_overall' => $r->health_snapshot['overall'] ?? null,
                'updated_at' => $r->updated_at?->toIso8601String(),
            ]);
    }

    /**
     * Customer billing plan performance (hotspot purchases by plan in range).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function planPerformanceReport(User $actor, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $q = HotspotPayment::query()
            ->select([
                'plan_id',
                DB::raw('COUNT(*) as purchase_count'),
                DB::raw("SUM(CASE WHEN status = 'authorized' THEN 1 ELSE 0 END) as authorized_count"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count"),
                DB::raw("SUM(CASE WHEN last_authorize_failed_at IS NOT NULL AND authorized_at IS NULL AND status = 'success' THEN 1 ELSE 0 END) as stuck_auth_count"),
            ])
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('plan_id');

        TenantReportingScope::constrainHotspotPayments($q, $this->routerIds($actor));

        $rows = $q->get();
        $planNames = CustomerBillingPlan::query()
            ->whereIn('id', $rows->pluck('plan_id')->filter())
            ->pluck('name', 'id');

        return $rows->map(function ($row) use ($planNames) {
            $purchases = (int) $row->purchase_count;
            $authOk = (int) $row->authorized_count;
            $rate = $purchases > 0 ? round(100 * $authOk / $purchases, 1) : 0.0;

            return [
                'plan_id' => $row->plan_id,
                'plan_name' => $planNames[$row->plan_id] ?? '—',
                'purchase_count' => $purchases,
                'authorized_count' => $authOk,
                'failed_count' => (int) $row->failed_count,
                'stuck_auth_count' => (int) $row->stuck_auth_count,
                'authorize_success_rate_pct' => $rate,
            ];
        })->sortByDesc('purchase_count')->values();
    }

    /**
     * Support / incidents — numeric summary suitable for export row + dashboard.
     *
     * @return array<string, mixed>
     */
    public function supportIncidentsReport(User $actor): array
    {
        $ownerId = TenantReportingScope::isPlatformAdmin($actor) ? null : $actor->id;

        $inc = $this->incidents->summarize($ownerId);

        $hpBase = HotspotPayment::query();
        TenantReportingScope::constrainHotspotPayments($hpBase, $this->routerIds($actor));

        $withPriorFailure = (clone $hpBase)->whereNotNull('first_authorize_failure_at');
        $failedCount = $withPriorFailure->count();
        $recoveredCount = (clone $withPriorFailure)->whereNotNull('recovered_after_failure_at')->count();
        $recoveryRate = $failedCount > 0
            ? round(100 * $recoveredCount / $failedCount, 1)
            : 100.0;

        $avgRetries = (float) ((clone $hpBase)
            ->where('authorize_attempts', '>', 0)
            ->avg('authorize_attempts') ?? 0);

        return array_merge($inc, [
            'recovery_rate_after_failure_pct' => $recoveryRate,
            'avg_authorize_attempts' => round($avgRetries, 2),
            'routers_needing_attention' => $this->countRoutersNeedingAttention($actor),
        ]);
    }

    private function countRoutersNeedingAttention(User $actor): int
    {
        $q = Router::query()->where(function ($qq) {
            $qq->where('credential_mismatch_suspected', true)
                ->orWhereIn('onboarding_status', [
                    RouterOnboarding::OFFLINE,
                    RouterOnboarding::BUNDLE_MISMATCH,
                    RouterOnboarding::CRED_MISMATCH,
                    RouterOnboarding::ERROR,
                ]);
        });

        TenantReportingScope::constrainRouters($q, $this->routerIds($actor));

        return $q->count();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function invoiceReport(User $actor, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        $inRange = function (Builder $q) use ($range): void {
            $q->where(function (Builder $qq) use ($range) {
                $qq->whereBetween('issued_at', $range)
                    ->orWhere(function (Builder $q2) use ($range) {
                        $q2->whereNull('issued_at')->whereBetween('created_at', $range);
                    });
            });
        };

        if (TenantReportingScope::isPlatformAdmin($actor)) {
            return Invoice::query()
                ->with(['customer', 'subscription.plan', 'subscription.router'])
                ->where($inRange)
                ->orderByDesc('issued_at')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Invoice $inv) => $this->invoiceRow($inv));
        }

        return $actor->invoices()
            ->with(['subscription.plan', 'subscription.router'])
            ->where($inRange)
            ->orderByDesc('issued_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Invoice $inv) => $this->invoiceRow($inv));
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceRow(Invoice $inv): array
    {
        return [
            'invoice_number' => $inv->invoice_number,
            'status' => $inv->status,
            'total' => (float) $inv->total,
            'currency' => $inv->currency,
            'issued_at' => $inv->issued_at?->toIso8601String(),
            'due_at' => $inv->due_at?->toIso8601String(),
            'customer_id' => $inv->customer_id,
            'customer_name' => $inv->customer?->name,
            'plan_name' => $inv->subscription?->plan?->name,
            'router_name' => $inv->subscription?->router?->name,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function voucherReport(User $actor, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $q = CustomerVoucher::query()
            ->with('plan')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        if (! TenantReportingScope::isPlatformAdmin($actor)) {
            $q->where('customer_id', $actor->id);
        }

        return $q->orderByDesc('created_at')
            ->get()
            ->map(fn (CustomerVoucher $v) => [
                'code' => $v->code,
                'batch_name' => $v->batch_name,
                'status' => $v->status,
                'plan_name' => $v->plan?->name,
                'used_at' => $v->used_at?->toIso8601String(),
                'expires_at' => $v->expires_at?->toIso8601String(),
                'created_at' => $v->created_at?->toIso8601String(),
                'customer_id' => $v->customer_id,
            ]);
    }
}
