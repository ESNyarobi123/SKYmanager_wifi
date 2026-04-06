<?php

namespace App\Data;

use Carbon\CarbonInterface;

/**
 * Normalized row for customer-facing client / session monitoring (subscriptions + hotspot payments).
 *
 * @phpstan-type SerializedClientSessionView array<string, mixed>
 */
readonly class ClientSessionView
{
    public function __construct(
        public string $id,
        public string $sourceType,
        public string $clientLabel,
        public ?string $routerId,
        public string $routerName,
        public ?string $routerSsid,
        public string $planName,
        public string $accessPresence,
        public string $presenceLabel,
        /** billing | last_known | historical | router_live | router_cached | router_polled */
        public string $dataTimeliness,
        public ?CarbonInterface $startedAt,
        public ?CarbonInterface $expiresAt,
        public ?CarbonInterface $lastActivityAt,
        public ?int $dataUsedMb,
        public ?int $dataQuotaMb,
        public bool $isActiveAccess,
        public bool $isPendingAccess,
        /** active | history */
        public string $segment,
        public ?string $reference = null,
        public ?string $amount = null,
        public ?string $speedProfile = null,
        public string $sourceLabel = '',
        public string $remainingLabel = '',
        public string $wifiAssociationLabel = '',
        public ?string $billingPlanId = null,
        public ?string $customerBillingPlanId = null,
        /** Normalized MAC for RouterOS matching */
        public ?string $clientMac = null,
        /** From hotspot_payments.router_* before live merge */
        public ?int $liveRouterBytesIn = null,
        public ?int $liveRouterBytesOut = null,
        public ?CarbonInterface $liveRouterUsageSyncedAt = null,
        public ?RouterLiveSnapshot $routerLive = null,
    ) {}

    public static function mergeRouterLive(self $v, RouterLiveSnapshot $live): self
    {
        $assoc = match ($live->state) {
            'live_fresh' => __('Online on hotspot (router). :label', ['label' => $live->freshnessLabel]),
            'live_stale' => __('Session listed on router; sync may be stale. :label', ['label' => $live->freshnessLabel]),
            'not_listed_fresh' => __('Not in router active hotspot list after a fresh sync — likely not on Wi‑Fi right now.'),
            'cached_payment' => __('Usage counters from a matched router session (stored on payment). :label', ['label' => $live->freshnessLabel]),
            default => __('Live association unknown. :label', ['label' => $live->freshnessLabel]),
        };

        $dataTimeliness = match ($live->state) {
            'live_fresh' => 'router_live',
            'live_stale' => 'router_cached',
            'not_listed_fresh' => 'router_live',
            'cached_payment' => 'router_polled',
            default => $v->dataTimeliness,
        };

        $dataUsedMb = $v->dataUsedMb;
        if ($v->sourceType === 'hotspot_payment'
            && $live->totalBytes() !== null
            && in_array($live->state, ['live_fresh', 'live_stale', 'cached_payment'], true)) {
            $dataUsedMb = (int) round($live->totalBytes() / 1048576);
        }

        return new self(
            id: $v->id,
            sourceType: $v->sourceType,
            clientLabel: $v->clientLabel,
            routerId: $v->routerId,
            routerName: $v->routerName,
            routerSsid: $v->routerSsid,
            planName: $v->planName,
            accessPresence: $v->accessPresence,
            presenceLabel: $v->presenceLabel,
            dataTimeliness: $dataTimeliness,
            startedAt: $v->startedAt,
            expiresAt: $v->expiresAt,
            lastActivityAt: $v->lastActivityAt,
            dataUsedMb: $dataUsedMb,
            dataQuotaMb: $v->dataQuotaMb,
            isActiveAccess: $v->isActiveAccess,
            isPendingAccess: $v->isPendingAccess,
            segment: $v->segment,
            reference: $v->reference,
            amount: $v->amount,
            speedProfile: $v->speedProfile,
            sourceLabel: $v->sourceLabel,
            remainingLabel: $v->remainingLabel,
            wifiAssociationLabel: $assoc,
            billingPlanId: $v->billingPlanId,
            customerBillingPlanId: $v->customerBillingPlanId,
            clientMac: $v->clientMac,
            liveRouterBytesIn: $v->liveRouterBytesIn,
            liveRouterBytesOut: $v->liveRouterBytesOut,
            liveRouterUsageSyncedAt: $v->liveRouterUsageSyncedAt,
            routerLive: $live,
        );
    }

    /**
     * @return SerializedClientSessionView
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->sourceType,
            'client_label' => $this->clientLabel,
            'router_id' => $this->routerId,
            'router_name' => $this->routerName,
            'router_ssid' => $this->routerSsid,
            'plan_name' => $this->planName,
            'access_presence' => $this->accessPresence,
            'presence_label' => $this->presenceLabel,
            'data_timeliness' => $this->dataTimeliness,
            'started_at' => $this->startedAt?->toIso8601String(),
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'last_activity_at' => $this->lastActivityAt?->toIso8601String(),
            'data_used_mb' => $this->dataUsedMb,
            'data_quota_mb' => $this->dataQuotaMb,
            'is_active_access' => $this->isActiveAccess,
            'is_pending_access' => $this->isPendingAccess,
            'segment' => $this->segment,
            'reference' => $this->reference,
            'amount' => $this->amount,
            'speed_profile' => $this->speedProfile,
            'source_label' => $this->sourceLabel,
            'remaining_label' => $this->remainingLabel,
            'wifi_association_label' => $this->wifiAssociationLabel,
            'billing_plan_id' => $this->billingPlanId,
            'customer_billing_plan_id' => $this->customerBillingPlanId,
            'client_mac' => $this->clientMac,
            'router_live_state' => $this->routerLive?->state,
            'router_live_bytes_in' => $this->routerLive?->bytesIn,
            'router_live_bytes_out' => $this->routerLive?->bytesOut,
            'router_live_synced_at' => $this->routerLive?->syncedAt?->toIso8601String(),
            'router_live_freshness' => $this->routerLive?->freshnessLabel,
        ];
    }
}
