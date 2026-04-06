<?php

namespace App\Data;

use Carbon\CarbonInterface;

/**
 * RouterOS / polled enrichment for a client session row (never replaces billing truth).
 *
 * @phpstan-type LiveState 'live_fresh'|'live_stale'|'not_listed_fresh'|'cached_payment'|'unknown'
 */
readonly class RouterLiveSnapshot
{
    /**
     * @param  LiveState  $state
     */
    public function __construct(
        public string $state,
        public ?int $bytesIn = null,
        public ?int $bytesOut = null,
        public ?int $uptimeSeconds = null,
        public ?string $uptimeRaw = null,
        public ?string $ipAddress = null,
        public ?string $userName = null,
        public ?CarbonInterface $syncedAt = null,
        public string $freshnessLabel = '',
    ) {}

    public function totalBytes(): ?int
    {
        if ($this->bytesIn === null && $this->bytesOut === null) {
            return null;
        }

        return (int) ($this->bytesIn ?? 0) + (int) ($this->bytesOut ?? 0);
    }
}
