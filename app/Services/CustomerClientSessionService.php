<?php

namespace App\Services;

use App\Data\ClientSessionView;
use App\Models\BillingPlan;
use App\Models\CustomerBillingPlan;
use App\Models\HotspotPayment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\RouterMacAddress;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds a tenant-scoped, unified read model of WiFi client access derived from
 * subscriptions (BillingPlan / portal subscriptions) and hotspot payments (CustomerBillingPlan).
 *
 * Access/billing windows are computed here; RouterOS rows are merged afterward via
 * {@see ClientSessionRouterLiveEnricher} when snapshots exist.
 */
class CustomerClientSessionService
{
    private const int HOTSPOT_PENDING_MAX_AGE_DAYS = 14;

    /**
     * @param  array{
     *     tab?: string,
     *     router_id?: string|null,
     *     search?: string,
     *     source_type?: string,
     *     plan_key?: string|null,
     *     access?: string,
     *     history_from?: Carbon|null,
     *     history_to?: Carbon|null,
     *     limit?: int,
     * }  $filters
     * @return Collection<int, ClientSessionView>
     */
    public function sessionsForCustomer(User $customer, array $filters = []): Collection
    {
        $routerIds = $customer->routers()->pluck('id');
        if ($routerIds->isEmpty()) {
            return collect();
        }

        $limit = (int) ($filters['limit'] ?? 800);

        $subscriptions = Subscription::query()
            ->whereIn('router_id', $routerIds)
            ->with(['wifiUser', 'plan', 'router'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $hotspotPayments = HotspotPayment::query()
            ->whereIn('router_id', $routerIds)
            ->with(['plan', 'router'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $views = collect($subscriptions)
            ->map(fn (Subscription $s) => $this->fromSubscription($s))
            ->merge(
                collect($hotspotPayments)->map(fn (HotspotPayment $h) => $this->fromHotspotPayment($h))
            );

        $filtered = $this->applyFilters($views, $filters);

        return app(ClientSessionRouterLiveEnricher::class)->enrich($filtered, $routerIds);
    }

    /**
     * @return array<string, int|string>
     */
    public function dashboardKpis(User $customer): array
    {
        $sessions = $this->sessionsForCustomer($customer, ['limit' => 500, 'tab' => 'all']);

        $activeValid = $sessions->filter(fn (ClientSessionView $v) => $v->segment === 'active' && $v->isActiveAccess && ! $v->isPendingAccess);
        $pending = $sessions->filter(fn (ClientSessionView $v) => $v->segment === 'active' && $v->isPendingAccess);
        $expiringSoon = $activeValid->filter(function (ClientSessionView $v) {
            if (! $v->expiresAt) {
                return false;
            }

            return $v->expiresAt->isFuture() && $v->expiresAt->lte(now()->addDay());
        });

        $routerWithMost = $activeValid
            ->groupBy('routerId')
            ->map(fn (Collection $g) => $g->count())
            ->sortDesc()
            ->keys()
            ->first();

        $topRouterName = '—';
        if ($routerWithMost) {
            $hit = $activeValid->firstWhere('routerId', $routerWithMost);
            $topRouterName = $hit?->routerName ?? '—';
        }

        $routerIds = $customer->routers()->pluck('id');
        $recordedUsageMb = $routerIds->isEmpty()
            ? 0
            : (int) Subscription::query()->whereIn('router_id', $routerIds)->sum('data_used_mb');

        return [
            'active_access_count' => $activeValid->count(),
            'pending_access_count' => $pending->count(),
            'expiring_24h_count' => $expiringSoon->count(),
            'top_active_router_label' => $topRouterName,
            'subscription_recorded_usage_mb' => $recordedUsageMb,
        ];
    }

    /**
     * Distinct plan options for filter dropdowns: value => label.
     *
     * @return array<string, string>
     */
    public function planOptionsForCustomer(User $customer): array
    {
        $opts = [];
        $routerIds = $customer->routers()->pluck('id');
        if ($routerIds->isEmpty()) {
            return [];
        }

        BillingPlan::query()
            ->whereHas('subscriptions', fn (Builder $q) => $q->whereIn('router_id', $routerIds))
            ->orderBy('name')
            ->get()
            ->each(function (BillingPlan $p) use (&$opts) {
                $opts['billing:'.$p->id] = $p->name;
            });

        $customerPlanIds = HotspotPayment::query()
            ->whereIn('router_id', $routerIds)
            ->distinct()
            ->pluck('plan_id');

        CustomerBillingPlan::query()
            ->where(function (Builder $q) use ($customer, $customerPlanIds) {
                $q->where('customer_id', $customer->id)
                    ->orWhereIn('id', $customerPlanIds);
            })
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->each(function (CustomerBillingPlan $p) use (&$opts) {
                $opts['customer:'.$p->id] = $p->name;
            });

        return $opts;
    }

    /**
     * @param  Collection<int, ClientSessionView>  $views
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ClientSessionView>
     */
    private function applyFilters(Collection $views, array $filters): Collection
    {
        $tab = $filters['tab'] ?? 'active';
        $routerId = $filters['router_id'] ?? null;
        $search = Str::lower(trim((string) ($filters['search'] ?? '')));
        $sourceType = $filters['source_type'] ?? 'all';
        $planKey = $filters['plan_key'] ?? null;
        $access = $filters['access'] ?? 'all';
        $historyFrom = $filters['history_from'] ?? null;
        $historyTo = $filters['history_to'] ?? null;

        $filtered = $views->values();

        if ($tab === 'active') {
            $filtered = $filtered->filter(fn (ClientSessionView $v) => $v->segment === 'active');
        } elseif ($tab === 'history') {
            $filtered = $filtered->filter(fn (ClientSessionView $v) => $v->segment === 'history');
        }
        // tab === 'all' keeps both segments

        if ($routerId) {
            $filtered = $filtered->filter(fn (ClientSessionView $v) => $v->routerId === $routerId);
        }

        if ($search !== '') {
            $filtered = $filtered->filter(function (ClientSessionView $v) use ($search) {
                return Str::contains(Str::lower($v->clientLabel), $search)
                    || Str::contains(Str::lower($v->planName), $search)
                    || ($v->reference && Str::contains(Str::lower($v->reference), $search));
            });
        }

        if ($sourceType !== 'all') {
            $filtered = $filtered->filter(fn (ClientSessionView $v) => $v->sourceType === $sourceType);
        }

        if ($planKey) {
            [$kind, $id] = array_pad(explode(':', $planKey, 2), 2, null);
            if ($kind && $id) {
                $filtered = $filtered->filter(function (ClientSessionView $v) use ($kind, $id) {
                    if ($kind === 'billing') {
                        return $v->billingPlanId === $id;
                    }
                    if ($kind === 'customer') {
                        return $v->customerBillingPlanId === $id;
                    }

                    return true;
                });
            }
        }

        if ($access === 'valid') {
            $filtered = $filtered->filter(fn (ClientSessionView $v) => $v->isActiveAccess && ! $v->isPendingAccess);
        } elseif ($access === 'pending') {
            $filtered = $filtered->filter(fn (ClientSessionView $v) => $v->isPendingAccess);
        } elseif ($access === 'none') {
            $filtered = $filtered->filter(fn (ClientSessionView $v) => ! $v->isActiveAccess && ! $v->isPendingAccess);
        }

        if ($tab === 'history' && ($historyFrom || $historyTo)) {
            $from = $historyFrom?->copy()->startOfDay();
            $to = $historyTo?->copy()->endOfDay();
            $filtered = $filtered->filter(function (ClientSessionView $v) use ($from, $to) {
                $at = $v->lastActivityAt ?? $v->expiresAt;
                if (! $at) {
                    return false;
                }
                if ($from && $at->lt($from)) {
                    return false;
                }
                if ($to && $at->gt($to)) {
                    return false;
                }

                return true;
            });
        }

        return $filtered->sortByDesc(function (ClientSessionView $v) {
            return $v->lastActivityAt?->timestamp
                ?? $v->expiresAt?->timestamp
                ?? $v->startedAt?->timestamp
                ?? 0;
        })->values();
    }

    private function fromSubscription(Subscription $s): ClientSessionView
    {
        $wifi = $s->wifiUser;
        $plan = $s->plan;
        $router = $s->router;

        $label = $this->wifiUserLabel($wifi?->phone_number, $wifi?->mac_address);
        $clientMac = RouterMacAddress::normalize($wifi?->mac_address);
        $planName = $plan?->name ?? '—';
        $expiresAt = $s->expires_at;
        $isActiveAccess = $s->status === 'active' && $expiresAt && $expiresAt->isFuture();
        $segment = $isActiveAccess ? 'active' : 'history';

        $presence = $this->subscriptionPresence($s);
        $quota = $plan?->data_quota_mb;
        $used = (int) ($s->data_used_mb ?? 0);

        $remaining = self::remainingLabel($expiresAt, $isActiveAccess, false);
        $speed = $plan ? $this->billingPlanSpeedLabel($plan) : null;

        return new ClientSessionView(
            id: 'sub:'.$s->id,
            sourceType: 'subscription',
            clientLabel: $label,
            routerId: $router?->id,
            routerName: $router?->name ?? '—',
            routerSsid: $router?->hotspot_ssid,
            planName: $planName,
            accessPresence: $presence['code'],
            presenceLabel: $presence['label'],
            dataTimeliness: 'last_known',
            startedAt: $s->created_at,
            expiresAt: $expiresAt,
            lastActivityAt: $s->updated_at,
            dataUsedMb: $used,
            dataQuotaMb: $quota,
            isActiveAccess: $isActiveAccess,
            isPendingAccess: false,
            segment: $segment,
            reference: null,
            amount: null,
            speedProfile: $speed,
            sourceLabel: __('Subscription'),
            remainingLabel: $remaining,
            wifiAssociationLabel: __('Not measured — access window from subscription record'),
            billingPlanId: $plan?->id,
            customerBillingPlanId: null,
            clientMac: $clientMac,
            liveRouterBytesIn: null,
            liveRouterBytesOut: null,
            liveRouterUsageSyncedAt: null,
            routerLive: null,
        );
    }

    private function fromHotspotPayment(HotspotPayment $h): ClientSessionView
    {
        $plan = $h->plan;
        $router = $h->router;

        $label = $this->wifiUserLabel($h->phone, $h->client_mac);
        $clientMac = RouterMacAddress::normalize($h->client_mac);
        $planName = $plan?->name ?? '—';
        $expiresAt = $h->expires_at;

        $segment = $this->hotspotSegment($h);
        $isActiveAccess = $h->status === 'authorized' && $expiresAt && $expiresAt->isFuture();
        $isPending = $segment === 'active' && ! $isActiveAccess;

        $presence = $this->hotspotPresence($h, $isActiveAccess, $isPending);
        $remaining = self::remainingLabel($expiresAt, $isActiveAccess, $isPending);

        $speed = $plan?->speedLabel();

        $routerMb = null;
        $dataTimeliness = 'historical';
        if ($h->router_bytes_in !== null && $h->router_bytes_out !== null) {
            $routerMb = (int) round(((int) $h->router_bytes_in + (int) $h->router_bytes_out) / 1048576);
            $dataTimeliness = 'router_polled';
        }

        return new ClientSessionView(
            id: 'hp:'.$h->id,
            sourceType: 'hotspot_payment',
            clientLabel: $label,
            routerId: $router?->id,
            routerName: $router?->name ?? '—',
            routerSsid: $router?->hotspot_ssid,
            planName: $planName,
            accessPresence: $presence['code'],
            presenceLabel: $presence['label'],
            dataTimeliness: $dataTimeliness,
            startedAt: $h->authorized_at ?? $h->created_at,
            expiresAt: $expiresAt,
            lastActivityAt: $h->updated_at,
            dataUsedMb: $routerMb,
            dataQuotaMb: $plan?->data_quota_mb,
            isActiveAccess: $isActiveAccess,
            isPendingAccess: $isPending,
            segment: $segment,
            reference: $h->reference,
            amount: (string) $h->amount,
            speedProfile: $speed,
            sourceLabel: __('Hotspot payment'),
            remainingLabel: $remaining,
            wifiAssociationLabel: __('Not measured — portal payment / authorization record'),
            billingPlanId: null,
            customerBillingPlanId: $plan?->id,
            clientMac: $clientMac,
            liveRouterBytesIn: $h->router_bytes_in,
            liveRouterBytesOut: $h->router_bytes_out,
            liveRouterUsageSyncedAt: $h->router_usage_synced_at,
            routerLive: null,
        );
    }

    private function hotspotSegment(HotspotPayment $h): string
    {
        if ($h->status === 'authorized') {
            if ($h->expires_at && $h->expires_at->isFuture()) {
                return 'active';
            }

            return 'history';
        }

        if ($h->status === 'failed') {
            return 'history';
        }

        if (in_array($h->status, ['pending', 'success'], true)) {
            if ($h->created_at->lt(now()->subDays(self::HOTSPOT_PENDING_MAX_AGE_DAYS))) {
                return 'history';
            }

            return 'active';
        }

        return 'history';
    }

    /**
     * @return array{code: string, label: string}
     */
    private function subscriptionPresence(Subscription $s): array
    {
        if ($s->status === 'expired' || ($s->expires_at && $s->expires_at->isPast())) {
            return [
                'code' => 'access_expired',
                'label' => __('Access expired (subscription)'),
            ];
        }

        if ($s->status === 'active' && $s->expires_at && $s->expires_at->isFuture()) {
            return [
                'code' => 'access_valid',
                'label' => __('Active access window'),
            ];
        }

        return [
            'code' => 'unknown',
            'label' => __('Unknown state'),
        ];
    }

    /**
     * @return array{code: string, label: string}
     */
    private function hotspotPresence(HotspotPayment $h, bool $isActiveAccess, bool $isPending): array
    {
        if ($h->status === 'failed') {
            return [
                'code' => 'access_failed',
                'label' => __('Payment or authorization failed'),
            ];
        }

        if ($isActiveAccess) {
            return [
                'code' => 'access_valid',
                'label' => __('Authorized — access window open'),
            ];
        }

        if ($isPending) {
            return [
                'code' => 'access_pending',
                'label' => __('Payment or router authorization in progress'),
            ];
        }

        if ($h->status === 'authorized' && $h->expires_at && $h->expires_at->isPast()) {
            return [
                'code' => 'access_expired',
                'label' => __('Authorization expired'),
            ];
        }

        return [
            'code' => 'historical',
            'label' => __('Closed / historical'),
        ];
    }

    private function wifiUserLabel(?string $phone, ?string $mac): string
    {
        $phone = $phone ? trim($phone) : '';
        $mac = $mac ? trim($mac) : '';

        if ($phone !== '' && $mac !== '') {
            return $phone.' · '.$mac;
        }

        if ($phone !== '') {
            return $phone;
        }

        if ($mac !== '') {
            return $mac;
        }

        return __('Unknown client');
    }

    private function billingPlanSpeedLabel(BillingPlan $plan): ?string
    {
        if (! $plan->upload_limit && ! $plan->download_limit) {
            return null;
        }

        return '↑'.$plan->upload_limit.'M / ↓'.$plan->download_limit.'M';
    }

    public static function remainingLabel(?CarbonInterface $expiresAt, bool $hasActiveWindow, bool $pending = false): string
    {
        if ($pending) {
            return __('Pending — assigned after authorization');
        }

        if (! $hasActiveWindow) {
            if ($expiresAt && $expiresAt->isPast()) {
                return __('Expired :t ago', ['t' => $expiresAt->diffForHumans(short: true)]);
            }

            return '—';
        }

        if (! $expiresAt) {
            return __('No expiry recorded');
        }

        if ($expiresAt->isFuture()) {
            return __(':t left', ['t' => $expiresAt->diffForHumans(parts: 2, short: true)]);
        }

        return __('Expired :t ago', ['t' => $expiresAt->diffForHumans(short: true)]);
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public function toCsvRows(Collection $sessions): array
    {
        $rows = [];
        foreach ($sessions as $s) {
            $rows[] = [
                $s->segment,
                $s->sourceLabel,
                $s->clientLabel,
                $s->routerName,
                $s->routerSsid ?? '',
                $s->planName,
                $s->presenceLabel,
                $s->wifiAssociationLabel,
                $s->remainingLabel,
                $s->startedAt?->toIso8601String() ?? '',
                $s->expiresAt?->toIso8601String() ?? '',
                $s->lastActivityAt?->toIso8601String() ?? '',
                $s->dataUsedMb !== null ? (string) $s->dataUsedMb : '',
                $s->dataQuotaMb !== null ? (string) $s->dataQuotaMb : '',
                $s->reference ?? '',
                $s->routerLive?->state ?? '',
                $s->routerLive?->bytesIn !== null ? (string) $s->routerLive->bytesIn : '',
                $s->routerLive?->bytesOut !== null ? (string) $s->routerLive->bytesOut : '',
                $s->routerLive?->syncedAt?->toIso8601String() ?? '',
                $s->routerLive?->freshnessLabel ?? '',
            ];
        }

        return $rows;
    }
}
