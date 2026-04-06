<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Hotspot payment authorizations') }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Provider-confirmed payments waiting on MikroTik hotspot authorization.') }}</p>
        </div>
        <flux:button :href="route('admin.router-operations')" variant="ghost" size="sm" wire:navigate icon="server">{{ __('Router operations') }}</flux:button>
    </div>

    @if($flashMessage)
        <div @class([
            'rounded-xl border p-4 text-sm',
            'bg-emerald-50 border-emerald-200 text-emerald-900' => $flashType === 'success',
            'bg-red-50 border-red-200 text-red-900' => $flashType === 'error',
        ])>{{ $flashMessage }}</div>
    @endif

    <div class="flex flex-wrap gap-3 items-end">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Reference, phone, router…')" icon="magnifying-glass" class="max-w-md w-full" />
        <flux:select wire:model.live="filter" class="w-56">
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            <flux:select.option value="pending">{{ __('Pending (provider)') }}</flux:select.option>
            <flux:select.option value="success">{{ __('Provider confirmed (success)') }}</flux:select.option>
            <flux:select.option value="authorized">{{ __('Authorized on router') }}</flux:select.option>
            <flux:select.option value="failed">{{ __('Failed') }}</flux:select.option>
            <flux:select.option value="stuck">{{ __('Stuck authorizing') }}</flux:select.option>
            <flux:select.option value="retry_exhausted">{{ __('Retry exhausted') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:card class="overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Router / customer') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Attempts') }}</flux:table.column>
                <flux:table.column>{{ __('Router context') }}</flux:table.column>
                <flux:table.column>{{ __('Last error') }}</flux:table.column>
                <flux:table.column>{{ __('Support') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($this->paginatedPayments() as $payment)
                    @php
                        $ctx = $this->routerContext($payment);
                        $r = $ctx['readiness'];
                        $liveHealth = $this->liveHealthEvaluate($payment);
                    @endphp
                    <flux:table.row :key="$payment->id">
                        <flux:table.cell>
                            <p class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($payment->reference, 20, '…') }}</p>
                            <p class="text-[10px] text-zinc-500">{{ $payment->phone }}</p>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">
                            <p>{{ $payment->router?->name ?? '—' }}</p>
                            <p class="text-xs text-zinc-500">{{ $payment->router?->user?->name ?? '—' }}</p>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="{{ $payment->status === 'authorized' ? 'green' : ($payment->status === 'failed' ? 'red' : 'zinc') }}">{{ $payment->status }}</flux:badge>
                            @if($payment->provider_confirmed_at)
                                <p class="text-[10px] text-zinc-500 mt-0.5">{{ __('Provider') }}: {{ $payment->provider_confirmed_at->diffForHumans() }}</p>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-xs font-mono">
                            {{ $payment->authorize_attempts }} / {{ config('skymanager.hotspot_authorize_max_attempts') }}
                        </flux:table.cell>
                        <flux:table.cell class="text-xs">
                            @if($payment->router)
                                <p>{{ __('Online') }}: {{ $ctx['router_online'] ? __('yes') : __('no') }}</p>
                                <p>{{ __('API') }}: {{ ($r['api_ok'] ?? false) ? 'ok' : '—' }} · {{ __('Tunnel') }}: {{ ($r['tunnel_ok'] ?? false) ? 'ok' : '—' }}</p>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-red-700 dark:text-red-400 max-w-xs line-clamp-3">{{ $payment->last_authorize_error ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm" variant="ghost" wire:click="toggleExpand('{{ $payment->id }}')">
                                {{ $this->expandedPaymentId === $payment->id ? __('Hide detail') : __('Open detail') }}
                            </flux:button>
                        </flux:table.cell>
                        <flux:table.cell>
                            @can('hotspot-payments.support')
                                @if($payment->status === 'success')
                                    <div class="flex flex-col gap-1">
                                        <flux:button size="sm" variant="ghost" wire:click="retryAuthorization('{{ $payment->id }}')"
                                            wire:confirm="{{ __('Queue authorization job again with attempts reset?') }}">{{ __('Retry now') }}</flux:button>
                                        <flux:button size="sm" variant="primary" wire:click="healthThenRetry('{{ $payment->id }}')"
                                            wire:confirm="{{ __('Run live health probe on router then retry authorization?') }}">{{ __('Health + retry') }}</flux:button>
                                        @if($payment->router)
                                            <flux:button size="sm" variant="ghost" :href="route('admin.router-operations.show', $payment->router_id)" wire:navigate>{{ __('Router') }}</flux:button>
                                        @endif
                                    </div>
                                @endif
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                    @if($this->expandedPaymentId === $payment->id)
                        <flux:table.row :key="$payment->id.'-detail'" class="bg-zinc-50/80 dark:bg-zinc-900/40">
                            <flux:table.cell colspan="8" class="align-top p-4 sm:p-6">
                                <div class="grid gap-6 lg:grid-cols-2">
                                    <div class="space-y-4">
                                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Support timeline') }}</h3>
                                        <ol class="space-y-2 text-xs text-zinc-600 dark:text-zinc-400 border-l-2 border-zinc-200 dark:border-zinc-700 pl-3">
                                            @foreach($this->paymentTimeline($payment) as $step)
                                                <li>
                                                    <span class="font-mono text-[10px] text-zinc-500">{{ $step['at'] ?? '—' }}</span>
                                                    <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ $step['label'] }}</p>
                                                    @if($step['detail'] !== '')
                                                        <p class="text-zinc-600 dark:text-zinc-400">{{ $step['detail'] }}</p>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ol>
                                        <div>
                                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-2">{{ __('Support hints') }}</h3>
                                            <ul class="list-disc pl-4 space-y-1 text-xs text-zinc-600 dark:text-zinc-400">
                                                @foreach($this->supportHints($payment) as $hint)
                                                    <li>{{ $hint }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="space-y-4">
                                        <div>
                                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-2">{{ __('At last failure (persisted)') }}</h3>
                                            <dl class="grid grid-cols-1 gap-1 text-xs text-zinc-600 dark:text-zinc-400">
                                                <div><dt class="inline font-medium text-zinc-500">{{ __('When') }}:</dt> <dd class="inline">{{ $payment->last_authorize_failed_at?->toIso8601String() ?? '—' }}</dd></div>
                                                <div><dt class="inline font-medium text-zinc-500">{{ __('Code') }}:</dt> <dd class="inline font-mono">{{ $payment->last_authorize_error_code ?? '—' }}</dd></div>
                                                <div><dt class="inline font-medium text-zinc-500">{{ __('Router online') }}:</dt> <dd class="inline">{{ $payment->last_failure_router_online === null ? '—' : ($payment->last_failure_router_online ? __('yes') : __('no')) }}</dd></div>
                                                <div><dt class="inline font-medium text-zinc-500">{{ __('Overall health') }}:</dt> <dd class="inline">{{ $payment->last_failure_overall_health ?? '—' }}</dd></div>
                                                <div><dt class="inline font-medium text-zinc-500">{{ __('Tunnel / API / portal') }}:</dt> <dd class="inline">{{ $payment->last_failure_tunnel_level ?? '—' }} / {{ $payment->last_failure_api_level ?? '—' }} / {{ $payment->last_failure_portal_level ?? '—' }}</dd></div>
                                                <div><dt class="inline font-medium text-zinc-500">{{ __('Router ready for authorize') }}:</dt> <dd class="inline">{{ $payment->router_ready_for_authorize_at_failure === null ? '—' : ($payment->router_ready_for_authorize_at_failure ? __('yes') : __('no')) }}</dd></div>
                                                <div><dt class="inline font-medium text-zinc-500">{{ __('Provider confirmed at failure') }}:</dt> <dd class="inline">{{ $payment->provider_confirmed_at_failure === null ? '—' : ($payment->provider_confirmed_at_failure ? __('yes') : __('no')) }}</dd></div>
                                                <div><dt class="inline font-medium text-zinc-500">{{ __('Retries exhausted at') }}:</dt> <dd class="inline">{{ $payment->authorize_retry_exhausted_at?->toIso8601String() ?? '—' }}</dd></div>
                                                <div><dt class="inline font-medium text-zinc-500">{{ __('Recovered after failure') }}:</dt> <dd class="inline">{{ $payment->recovered_after_failure_at?->toIso8601String() ?? '—' }}</dd></div>
                                                <div><dt class="inline font-medium text-zinc-500">{{ __('Admin retries') }}:</dt> <dd class="inline">{{ $payment->admin_authorize_retry_count }}</dd></div>
                                            </dl>
                                        </div>
                                        @if($payment->last_authorize_health_snapshot)
                                            <div>
                                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-2">{{ __('Failure health snapshot (JSON)') }}</h3>
                                                <pre class="text-[10px] leading-relaxed max-h-56 overflow-auto rounded-lg bg-zinc-100 dark:bg-zinc-950 p-3 text-zinc-800 dark:text-zinc-300 whitespace-pre-wrap break-words">{{ json_encode($payment->last_authorize_health_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </div>
                                        @endif
                                        <div>
                                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-2">{{ __('Live evaluation (now)') }}</h3>
                                            @if($liveHealth)
                                                <dl class="grid grid-cols-1 gap-1 text-xs text-zinc-600 dark:text-zinc-400">
                                                    <div><dt class="inline font-medium text-zinc-500">{{ __('Overall') }}:</dt> <dd class="inline">{{ $liveHealth['overall'] ?? '—' }}</dd></div>
                                                    <div><dt class="inline font-medium text-zinc-500">{{ __('Tunnel') }}:</dt> <dd class="inline">{{ $liveHealth['tunnel']['level'] ?? '—' }}</dd></div>
                                                    <div><dt class="inline font-medium text-zinc-500">{{ __('API') }}:</dt> <dd class="inline">{{ $liveHealth['api']['level'] ?? '—' }}</dd></div>
                                                    <div><dt class="inline font-medium text-zinc-500">{{ __('Portal') }}:</dt> <dd class="inline">{{ $liveHealth['portal']['level'] ?? '—' }}</dd></div>
                                                </dl>
                                            @else
                                                <p class="text-xs text-zinc-500">{{ __('No router attached — live health unavailable.') }}</p>
                                            @endif
                                            @if($payment->router)
                                                @php $lr = $ctx['readiness']; @endphp
                                                <p class="text-xs text-zinc-500 mt-2">{{ __('Readiness') }}: {{ __('tunnel') }} {{ ($lr['tunnel_ok'] ?? false) ? 'ok' : '—' }}, {{ __('API') }} {{ ($lr['api_ok'] ?? false) ? 'ok' : '—' }}, {{ __('authorize likely') }} {{ ($lr['payment_authorize_likely'] ?? false) ? __('yes') : __('no') }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endif
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-zinc-500 py-10">{{ __('No payments match.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="px-4 py-3 border-t border-zinc-100 dark:border-zinc-800">
            {{ $this->paginatedPayments()->links() }}
        </div>
    </flux:card>
</div>
