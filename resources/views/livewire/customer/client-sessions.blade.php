<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">{{ __('Client sessions & usage') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-neutral-500 max-w-2xl">
                {{ __('Access windows and usage from subscriptions and hotspot payments on your routers. This is not a live Wi‑Fi association list unless your deployment adds RouterOS session polling.') }}
            </p>
        </div>
        <flux:button
            :href="route('customer.client-sessions.export', array_filter([
                'tab' => $tab,
                'router_id' => $routerId,
                'search' => $search,
                'source_type' => $sourceType,
                'plan_key' => $planKey ?: null,
                'access' => $access,
                'history_from' => $tab === 'history' ? $historyFrom : null,
                'history_to' => $tab === 'history' ? $historyTo : null,
            ]))"
            variant="ghost"
            size="sm"
            icon="arrow-down-tray"
        >
            {{ __('Export CSV') }}
        </flux:button>
    </div>

    <flux:callout variant="secondary" icon="information-circle" class="mb-6">
        <strong class="font-semibold">{{ __('How to read this page') }}</strong>
        <span class="block mt-1 text-sm opacity-90">
            {{ __('“Active access” means a valid time window in SKYmanager (subscription or authorized hotspot payment). It does not guarantee the device is currently associated to Wi‑Fi. Usage for subscriptions is the last counter stored in SKYmanager; hotspot rows do not include per-session bytes until that data is synced.') }}
        </span>
    </flux:callout>

    <div class="flex flex-wrap gap-2 mb-6">
        <flux:button wire:click="$set('tab', 'active')" :variant="$tab === 'active' ? 'primary' : 'ghost'" size="sm">
            {{ __('Active') }}
        </flux:button>
        <flux:button wire:click="$set('tab', 'history')" :variant="$tab === 'history' ? 'primary' : 'ghost'" size="sm">
            {{ __('History') }}
        </flux:button>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-4 mb-6">
        <flux:field>
            <flux:label>{{ __('Search') }}</flux:label>
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Phone, MAC, plan, reference…') }}" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Router') }}</flux:label>
            <flux:select wire:model.live="routerId" size="sm">
                <flux:select.option value="">{{ __('All routers') }}</flux:select.option>
                @foreach($routers as $r)
                    <flux:select.option value="{{ $r->id }}">{{ $r->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Source') }}</flux:label>
            <flux:select wire:model.live="sourceType" size="sm">
                <flux:select.option value="all">{{ __('All sources') }}</flux:select.option>
                <flux:select.option value="subscription">{{ __('Subscription') }}</flux:select.option>
                <flux:select.option value="hotspot_payment">{{ __('Hotspot payment') }}</flux:select.option>
            </flux:select>
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Access state') }}</flux:label>
            <flux:select wire:model.live="access" size="sm">
                <flux:select.option value="all">{{ __('Any') }}</flux:select.option>
                <flux:select.option value="valid">{{ __('Valid access window') }}</flux:select.option>
                <flux:select.option value="pending">{{ __('Pending authorization') }}</flux:select.option>
                <flux:select.option value="none">{{ __('No valid window') }}</flux:select.option>
            </flux:select>
        </flux:field>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 mb-6">
        <flux:field>
            <flux:label>{{ __('Plan') }}</flux:label>
            <flux:select wire:model.live="planKey" size="sm">
                <flux:select.option value="">{{ __('All plans') }}</flux:select.option>
                @foreach($this->planOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
        @if($tab === 'history')
            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('From') }}</flux:label>
                    <flux:input type="date" wire:model.live="historyFrom" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('To') }}</flux:label>
                    <flux:input type="date" wire:model.live="historyTo" />
                </flux:field>
            </div>
        @endif
    </div>

    <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 overflow-hidden">
        <div class="overflow-x-auto">
            @if($sessions->isEmpty())
                <div class="px-5 py-16 text-center text-sm text-gray-500 dark:text-neutral-400">
                    {{ __('No rows match your filters.') }}
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-neutral-700/30">
                        <tr>
                            <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Client') }}</th>
                            <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Router / SSID') }}</th>
                            <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Source') }}</th>
                            <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Access') }}</th>
                            <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Router session') }}</th>
                            <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Remaining') }}</th>
                            <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Usage') }}</th>
                            <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Timeline') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                        @foreach($sessions as $row)
                            @php
                                $quota = $row->dataQuotaMb;
                                $used = $row->dataUsedMb;
                                $quotaPct = ($quota && $used !== null && $quota > 0) ? min(100, round(($used / $quota) * 100)) : null;
                                $durationEnd = $row->expiresAt;
                                if ($row->segment === 'active' && $row->isActiveAccess && (! $durationEnd || $durationEnd->isFuture())) {
                                    $durationEnd = now();
                                }
                                $durationLabel = $row->startedAt && $durationEnd
                                    ? $row->startedAt->diffForHumans($durationEnd, true, true, 2)
                                    : '—';
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-neutral-700/20 align-top">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800 dark:text-neutral-200">{{ $row->clientLabel }}</p>
                                    <p class="text-xs text-gray-400 dark:text-neutral-500 mt-0.5">{{ $row->wifiAssociationLabel }}</p>
                                    @if($row->speedProfile)
                                        <p class="text-xs text-sky-600 dark:text-sky-400 mt-1">{{ $row->speedProfile }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-neutral-300">
                                    <p>{{ $row->routerName }}</p>
                                    <p class="text-xs text-gray-400 dark:text-neutral-500">{{ $row->routerSsid ?: '—' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" color="zinc">{{ $row->sourceLabel }}</flux:badge>
                                    <p class="text-xs text-gray-500 dark:text-neutral-400 mt-1">{{ $row->planName }}</p>
                                    @if($row->reference)
                                        <p class="text-[10px] font-mono text-gray-400 mt-0.5 break-all">{{ $row->reference }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $badgeColor = match ($row->accessPresence) {
                                            'access_valid' => 'green',
                                            'access_pending' => 'amber',
                                            'access_expired', 'access_failed' => 'red',
                                            default => 'zinc',
                                        };
                                    @endphp
                                    <flux:badge size="sm" :color="$badgeColor">{{ $row->presenceLabel }}</flux:badge>
                                    @php
                                        $dtLabel = match ($row->dataTimeliness) {
                                            'last_known' => __('Usage: last known (subscription counter)'),
                                            'router_live' => __('Session data: live router poll'),
                                            'router_cached' => __('Session data: router (sync may be stale)'),
                                            'router_polled' => __('Usage: matched router counters'),
                                            'historical' => __('Usage: not tracked in SKYmanager'),
                                            default => __('Usage: see usage column'),
                                        };
                                    @endphp
                                    <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-wide">{{ $dtLabel }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-neutral-400 max-w-[200px]">
                                    @if($row->routerLive)
                                        @php $rl = $row->routerLive; @endphp
                                        @if($rl->state === 'live_fresh')
                                            <flux:badge size="sm" color="green">{{ __('Online now (router)') }}</flux:badge>
                                        @elseif($rl->state === 'live_stale')
                                            <flux:badge size="sm" color="amber">{{ __('Listed on router (old sync)') }}</flux:badge>
                                        @elseif($rl->state === 'not_listed_fresh')
                                            <flux:badge size="sm" color="zinc">{{ __('Not in active list') }}</flux:badge>
                                        @elseif($rl->state === 'cached_payment')
                                            <flux:badge size="sm" color="sky">{{ __('Stored router usage') }}</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc">{{ __('Unknown') }}</flux:badge>
                                        @endif
                                        <p class="text-[10px] text-gray-500 mt-1 leading-snug">{{ $rl->freshnessLabel }}</p>
                                        @if($rl->bytesIn !== null || $rl->bytesOut !== null)
                                            <p class="text-[11px] font-mono text-gray-700 dark:text-neutral-300 mt-1">
                                                ↓{{ number_format((($rl->bytesIn ?? 0) / 1048576), 2) }}
                                                ↑{{ number_format((($rl->bytesOut ?? 0) / 1048576), 2) }} MB
                                            </p>
                                        @endif
                                        @if($rl->uptimeSeconds)
                                            <p class="text-[10px] text-gray-500">{{ __('Uptime') }}: {{ $rl->uptimeSeconds }}s</p>
                                        @elseif($rl->uptimeRaw)
                                            <p class="text-[10px] text-gray-500">{{ __('Uptime') }}: {{ $rl->uptimeRaw }}</p>
                                        @endif
                                        @if($rl->ipAddress)
                                            <p class="text-[10px] font-mono text-gray-500">{{ $rl->ipAddress }}</p>
                                        @endif
                                        @if($rl->userName)
                                            <p class="text-[10px] text-gray-500">{{ __('User') }}: {{ $rl->userName }}</p>
                                        @endif
                                    @else
                                        <p class="text-gray-400 dark:text-neutral-500">{{ __('No router session data') }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-neutral-300 whitespace-nowrap">
                                    {{ $row->remainingLabel }}
                                </td>
                                <td class="px-4 py-3 min-w-[140px]">
                                    @if($row->sourceType === 'subscription' && $row->routerLive && ($row->routerLive->bytesIn !== null || $row->routerLive->bytesOut !== null))
                                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">{{ __('Billing counter') }}</p>
                                    @endif
                                    @if($used !== null)
                                        <p class="text-gray-800 dark:text-neutral-200">{{ number_format($used) }} MB</p>
                                    @else
                                        <p class="text-gray-400 dark:text-neutral-500">{{ __('Not recorded') }}</p>
                                    @endif
                                    @if($quota)
                                        <p class="text-xs text-gray-500 dark:text-neutral-400 mt-0.5">{{ __('Quota') }}: {{ number_format($quota) }} MB</p>
                                        @if($quotaPct !== null)
                                            <div class="mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-neutral-700 overflow-hidden">
                                                <div class="h-full rounded-full {{ $quotaPct >= 100 ? 'bg-red-500' : 'bg-sky-500' }}" style="width: {{ $quotaPct }}%"></div>
                                            </div>
                                            @if($used !== null && $used >= $quota)
                                                <p class="text-[10px] text-red-600 dark:text-red-400 mt-1">{{ __('Quota exhausted (counter)') }}</p>
                                            @endif
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-neutral-400">
                                    <p><span class="text-gray-400">{{ __('Started') }}</span> {{ $row->startedAt?->timezone(config('app.timezone'))->format('M j, H:i') ?? '—' }}</p>
                                    <p class="mt-0.5"><span class="text-gray-400">{{ __('Expires') }}</span> {{ $row->expiresAt?->timezone(config('app.timezone'))->format('M j, H:i') ?? '—' }}</p>
                                    <p class="mt-0.5"><span class="text-gray-400">{{ __('Last activity') }}</span> {{ $row->lastActivityAt?->timezone(config('app.timezone'))->format('M j, H:i') ?? '—' }}</p>
                                    <p class="mt-0.5"><span class="text-gray-400">{{ __('Duration') }}</span> {{ $durationLabel }}</p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
        @if($sessions->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 dark:border-neutral-700">
                {{ $sessions->links() }}
            </div>
        @endif
    </div>
</div>
