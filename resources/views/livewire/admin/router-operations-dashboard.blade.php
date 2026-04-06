@php
    use App\Support\RouterOnboarding;
    use App\Support\RouterOperationalReadiness;
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Router operations') }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Health, onboarding, and bundle visibility across customer routers.') }}</p>
        </div>
        <flux:button :href="route('admin.hotspot-payment-support')" variant="ghost" size="sm" icon="banknotes" wire:navigate>
            {{ __('Payment authorizations') }}
        </flux:button>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/80 dark:bg-amber-950/20 px-3 py-2">
            <p class="text-[10px] font-semibold uppercase text-amber-800 dark:text-amber-300">{{ __('Long claim') }}</p>
            <p class="text-lg font-semibold text-amber-900 dark:text-amber-100">{{ $this->incidentSummary['long_claimed'] }}</p>
            <p class="text-[10px] text-amber-700/80 dark:text-amber-400/80">{{ __('>72h claimed') }}</p>
        </div>
        <div class="rounded-xl border border-orange-200 dark:border-orange-900/50 bg-orange-50/80 dark:bg-orange-950/20 px-3 py-2">
            <p class="text-[10px] font-semibold uppercase text-orange-800 dark:text-orange-300">{{ __('Tunnel stuck') }}</p>
            <p class="text-lg font-semibold text-orange-900 dark:text-orange-100">{{ $this->incidentSummary['tunnel_stuck'] }}</p>
            <p class="text-[10px] text-orange-700/80">{{ __('24h+ pending') }}</p>
        </div>
        <div class="rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50/80 dark:bg-red-950/20 px-3 py-2">
            <p class="text-[10px] font-semibold uppercase text-red-800 dark:text-red-300">{{ __('Cred flag') }}</p>
            <p class="text-lg font-semibold text-red-900 dark:text-red-100">{{ $this->incidentSummary['cred_flags'] }}</p>
        </div>
        <div class="rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/80 dark:bg-rose-950/20 px-3 py-2">
            <p class="text-[10px] font-semibold uppercase text-rose-800 dark:text-rose-300">{{ __('Bundle mismatch') }}</p>
            <p class="text-lg font-semibold text-rose-900 dark:text-rose-100">{{ $this->incidentSummary['bundle_mismatch'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/80 dark:bg-zinc-900/30 px-3 py-2">
            <p class="text-[10px] font-semibold uppercase text-zinc-600 dark:text-zinc-400">{{ __('Routers offline') }}</p>
            <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->incidentSummary['routers_offline'] }}</p>
        </div>
        <div class="rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/80 dark:bg-amber-950/20 px-3 py-2">
            <p class="text-[10px] font-semibold uppercase text-amber-800 dark:text-amber-300">{{ __('Hotspot stuck') }}</p>
            <p class="text-lg font-semibold text-amber-900 dark:text-amber-100">{{ $this->incidentSummary['hotspot_stuck'] }}</p>
        </div>
        <div class="rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50/80 dark:bg-red-950/20 px-3 py-2">
            <p class="text-[10px] font-semibold uppercase text-red-800 dark:text-red-300">{{ __('Hotspot retry exhausted') }}</p>
            <p class="text-lg font-semibold text-red-900 dark:text-red-100">{{ $this->incidentSummary['hotspot_retry_exhausted'] }}</p>
        </div>
        <div class="rounded-xl border border-violet-200 dark:border-violet-900/50 bg-violet-50/80 dark:bg-violet-950/20 px-3 py-2">
            <p class="text-[10px] font-semibold uppercase text-violet-800 dark:text-violet-300">{{ __('Auth failures 24h') }}</p>
            <p class="text-lg font-semibold text-violet-900 dark:text-violet-100">{{ $this->incidentSummary['hotspot_failures_24h'] }}</p>
        </div>
    </div>

    <p class="text-xs text-zinc-500 dark:text-zinc-400">
        {{ __('Paid, not yet on router') }}: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $this->incidentSummary['hotspot_provider_pending_auth'] }}</span>
        <span class="mx-2">·</span>
        <flux:link :href="route('admin.hotspot-payment-support')" wire:navigate>{{ __('Open payment authorizations') }}</flux:link>
    </p>

    <div class="flex flex-wrap gap-3 items-end">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search name, ULID, MAC, IP, customer…')" icon="magnifying-glass" class="max-w-md w-full" />
        <flux:select wire:model.live="filter" class="w-52">
            <flux:select.option value="all">{{ __('All routers') }}</flux:select.option>
            <flux:select.option value="healthy">{{ __('Health: healthy') }}</flux:select.option>
            <flux:select.option value="warning">{{ __('Health: warning') }}</flux:select.option>
            <flux:select.option value="error">{{ __('Health: error') }}</flux:select.option>
            <flux:select.option value="unknown">{{ __('Health: unknown') }}</flux:select.option>
            <flux:select.option value="ready">{{ __('Onboarding: ready') }}</flux:select.option>
            <flux:select.option value="claimed">{{ __('Onboarding: claimed') }}</flux:select.option>
            <flux:select.option value="tunnel_pending">{{ __('Tunnel pending') }}</flux:select.option>
            <flux:select.option value="api_failed">{{ __('API / offline') }}</flux:select.option>
            <flux:select.option value="bundle_mismatch">{{ __('Bundle mismatch') }}</flux:select.option>
            <flux:select.option value="cred_mismatch">{{ __('Credential mismatch') }}</flux:select.option>
            <flux:select.option value="offline">{{ __('Offline status') }}</flux:select.option>
            <flux:select.option value="legacy_mode">{{ __('Legacy / non-bundle') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:card class="overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Router') }}</flux:table.column>
                <flux:table.column>{{ __('Customer') }}</flux:table.column>
                <flux:table.column>{{ __('Onboarding') }}</flux:table.column>
                <flux:table.column>{{ __('Health') }}</flux:table.column>
                <flux:table.column>{{ __('Tunnel / API') }}</flux:table.column>
                <flux:table.column>{{ __('Bundle') }}</flux:table.column>
                <flux:table.column>{{ __('Last contact') }}</flux:table.column>
                <flux:table.column>{{ __('Hotspot sessions') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($this->paginatedRouters() as $router)
                    @php
                        $readiness = RouterOperationalReadiness::snapshot($router);
                        $overall = $readiness['health_overall'];
                        $healthColor = match ($overall) {
                            'healthy' => 'green',
                            'warning' => 'amber',
                            'error' => 'red',
                            default => 'zinc',
                        };
                        $bundleLabel = match ($readiness['bundle_mode']) {
                            'bundle' => __('Bundle'),
                            'legacy' => __('Legacy'),
                            default => __('Unknown'),
                        };
                    @endphp
                    <flux:table.row :key="$router->id">
                        <flux:table.cell>
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $router->name }}</p>
                            <p class="text-xs font-mono text-zinc-500">{{ \Illuminate\Support\Str::limit($router->id, 18, '…') }}</p>
                            @if($router->last_error_message)
                                <p class="text-[10px] text-red-600 dark:text-red-400 line-clamp-2 mt-1" title="{{ $router->last_error_message }}">{{ $router->last_error_message }}</p>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">
                            {{ $router->user?->name ?? '—' }}
                            @if($router->user?->phone)
                                <p class="text-xs text-zinc-500 font-mono">{{ $router->user->phone }}</p>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ RouterOnboarding::label($router->onboarding_status ?? '') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$healthColor">{{ $overall }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="text-xs space-y-0.5">
                                <span class="@if($readiness['tunnel_ok']) text-emerald-600 @else text-zinc-500 @endif">{{ __('Tunnel') }}</span>
                                <span class="text-zinc-400">·</span>
                                <span class="@if($readiness['api_ok']) text-emerald-600 @else text-zinc-500 @endif">{{ __('API') }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="{{ $readiness['bundle_mode'] === 'bundle' ? 'green' : 'amber' }}">{{ $bundleLabel }}</flux:badge>
                            @if($router->portal_bundle_version)
                                <p class="text-[10px] text-zinc-500 mt-0.5 font-mono">v{{ $router->portal_bundle_version }}</p>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500">
                            <p>{{ __('API') }}: {{ $router->last_api_success_at?->diffForHumans() ?? '—' }}</p>
                            <p>{{ __('Health') }}: {{ $router->health_evaluated_at?->diffForHumans() ?? '—' }}</p>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500">
                            @if($router->hotspot_sessions_synced_at)
                                <p class="text-emerald-600 dark:text-emerald-400">{{ $router->hotspot_sessions_synced_at->diffForHumans() }}</p>
                            @else
                                <p>—</p>
                            @endif
                            @if($router->hotspot_sessions_sync_error && ! $router->hotspot_sessions_synced_at)
                                <p class="text-[10px] text-red-600 line-clamp-2 mt-0.5" title="{{ $router->hotspot_sessions_sync_error }}">{{ __('Sync error') }}</p>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm" variant="ghost" :href="route('admin.router-operations.show', $router)" wire:navigate icon="chevron-right">
                                {{ __('Open') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9" class="text-center text-zinc-500 py-10">{{ __('No routers match.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="px-4 py-3 border-t border-zinc-100 dark:border-zinc-800">
            {{ $this->paginatedRouters()->links() }}
        </div>
    </flux:card>
</div>
