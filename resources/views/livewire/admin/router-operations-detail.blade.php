@php
    use App\Support\RouterOnboarding;
@endphp

@php
    $readiness = $this->readinessSnapshot();
    $eval = $this->healthEvaluateFresh();
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:button :href="route('admin.router-operations')" wire:navigate variant="ghost" size="sm" class="mb-2">{{ __('← Operations') }}</flux:button>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $router->name }}</h1>
            <p class="text-sm font-mono text-zinc-500">{{ $router->id }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:badge color="{{ $router->is_online ? 'green' : 'zinc' }}">{{ $router->is_online ? __('Online') : __('Offline') }}</flux:badge>
            <flux:badge color="zinc">{{ RouterOnboarding::label($router->onboarding_status ?? '') }}</flux:badge>
            <flux:badge color="{{ $readiness['bundle_mode'] === 'bundle' ? 'green' : 'amber' }}">
                {{ $readiness['bundle_mode'] === 'bundle' ? __('Bundle mode') : __('Legacy / unknown mode') }}
            </flux:badge>
        </div>
    </div>

    @if($flashMessage)
        <div @class([
            'rounded-xl border p-4 text-sm',
            'bg-emerald-50 border-emerald-200 text-emerald-900 dark:bg-emerald-950/30 dark:border-emerald-900' => $flashType === 'success',
            'bg-red-50 border-red-200 text-red-900 dark:bg-red-950/30 dark:border-red-900' => $flashType === 'error',
        ])>
            {{ $flashMessage }}
        </div>
    @endif

    <div class="rounded-xl border border-sky-200 dark:border-sky-900/50 bg-sky-50/60 dark:bg-sky-950/20 p-4">
        <p class="text-xs font-semibold uppercase text-sky-800 dark:text-sky-300 mb-2">{{ __('Support hints') }}</p>
        <ul class="list-disc ps-4 text-sm text-sky-900 dark:text-sky-200 space-y-1">
            @foreach($this->supportHintsList() as $hint)
                <li>{{ $hint }}</li>
            @endforeach
        </ul>
    </div>

    <div>
        <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">{{ __('Readiness') }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
            @foreach([
                'popup_ok' => __('Popup / portal files'),
                'payment_gateway_ok' => __('Payment gateway'),
                'tunnel_ok' => __('Tunnel'),
                'api_ok' => __('API auth'),
                'payment_authorize_likely' => __('Authorize likely'),
                'production_ready' => __('Production'),
            ] as $key => $label)
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2 text-xs">
                    <p class="text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                    <p class="font-semibold mt-0.5 {{ ($readiness[$key] ?? false) ? 'text-emerald-600' : 'text-zinc-400' }}">
                        {{ ($readiness[$key] ?? false) ? __('Yes') : __('No') }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    <div>
        <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">{{ __('Stored health snapshot') }}</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach(['onboarding' => __('Onboarding'), 'tunnel' => __('Tunnel'), 'api' => __('API'), 'portal' => __('Portal')] as $dim => $title)
                @php $d = $router->health_snapshot[$dim] ?? null; @endphp
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-3 text-sm">
                    <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ $title }}</p>
                    @if(is_array($d))
                        <flux:badge size="sm" class="mt-1" color="{{ ($d['level'] ?? '') === 'healthy' ? 'green' : (($d['level'] ?? '') === 'error' ? 'red' : 'zinc') }}">{{ $d['level'] ?? '—' }}</flux:badge>
                        <p class="text-xs text-zinc-500 mt-2">{{ $d['detail'] ?? '' }}</p>
                    @else
                        <p class="text-xs text-zinc-400 mt-1">{{ __('No snapshot — run recalculate.') }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div>
        <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">{{ __('Live evaluate (read-only)') }}</h2>
        <div class="grid sm:grid-cols-2 gap-3 text-xs font-mono bg-zinc-950 text-zinc-300 rounded-xl p-4 overflow-x-auto">
            <div>
                <p class="text-zinc-500 mb-1">{{ __('Overall') }}: {{ $eval['overall'] ?? '—' }}</p>
                <p class="text-zinc-500">{{ __('Suggested onboarding') }}: {{ $eval['suggested_onboarding_status'] ?? '—' }}</p>
            </div>
        </div>
    </div>

    <div>
        <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">{{ __('Diagnostics') }}</h2>
        <div class="grid sm:grid-cols-2 gap-4 text-sm">
            <flux:card class="p-4 space-y-2">
                <p class="text-xs font-semibold uppercase text-zinc-500">{{ __('WireGuard') }}</p>
                <p><span class="text-zinc-500">{{ __('Mode') }}:</span> {{ $router->preferred_vpn_mode ?? '—' }}</p>
                <p><span class="text-zinc-500">{{ __('wg_address') }}:</span> <span class="font-mono">{{ $router->wg_address ?? '—' }}</span></p>
                <p><span class="text-zinc-500">{{ __('Last handshake') }}:</span> {{ $router->wg_last_handshake_at?->diffForHumans() ?? '—' }}</p>
                <p><span class="text-zinc-500">{{ __('last_tunnel_ok') }}:</span> {{ json_encode($router->last_tunnel_ok) }}</p>
            </flux:card>
            <flux:card class="p-4 space-y-2">
                <p class="text-xs font-semibold uppercase text-zinc-500">{{ __('API') }}</p>
                <p><span class="text-zinc-500">{{ __('User / port') }}:</span> <span class="font-mono">{{ $router->api_username }} : {{ $router->api_port }}</span></p>
                <p><span class="text-zinc-500">{{ __('Credential version') }}:</span> {{ $router->api_credential_version ?? 0 }}</p>
                <p><span class="text-zinc-500">{{ __('Mismatch flag') }}:</span> {{ $router->credential_mismatch_suspected ? __('Yes') : __('No') }}</p>
                <p><span class="text-zinc-500">{{ __('last_api_error') }}:</span> {{ \Illuminate\Support\Str::limit($router->last_api_error ?? '—', 120) }}</p>
            </flux:card>
            <flux:card class="p-4 space-y-2 sm:col-span-2">
                <p class="text-xs font-semibold uppercase text-zinc-500">{{ __('Hotspot bundle') }}</p>
                <p><span class="text-zinc-500">{{ __('Folder') }}:</span> <span class="font-mono">{{ $router->portal_folder_name ?? '—' }}</span></p>
                <p><span class="text-zinc-500">{{ __('Version / hash') }}:</span> {{ $router->portal_bundle_version ?? '—' }} / {{ \Illuminate\Support\Str::limit($router->portal_bundle_hash ?? '—', 24, '…') }}</p>
                <p><span class="text-zinc-500">{{ __('deployment_mode') }}:</span> {{ $router->bundle_deployment_mode ?? '—' }}</p>
            </flux:card>
            <flux:card class="p-4 space-y-2 sm:col-span-2">
                <p class="text-xs font-semibold uppercase text-zinc-500">{{ __('Default / script assumptions') }}</p>
                @php $w = $router->onboarding_warnings ?? []; @endphp
                @if($w === [])
                    <p class="text-zinc-400">{{ __('No warnings stored.') }}</p>
                @else
                    <ul class="list-disc ps-4 text-xs text-zinc-600 dark:text-zinc-400 space-y-1">
                        @foreach(\Illuminate\Support\Arr::flatten($w) as $line)
                            @if(is_string($line) && $line !== '')
                                <li>{{ $line }}</li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </flux:card>
        </div>
    </div>

    <div>
        <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">{{ __('Hotspot active sessions (stored snapshot)') }}</h2>
        <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-3">
            {{ __('Last successful sync') }}:
            <span class="font-mono">{{ $router->hotspot_sessions_synced_at?->toDateTimeString() ?? '—' }}</span>
            @if($router->hotspot_sessions_sync_error)
                <span class="block mt-1 text-red-600 dark:text-red-400">{{ \Illuminate\Support\Str::limit($router->hotspot_sessions_sync_error, 240) }}</span>
            @endif
        </p>
        @if($this->storedHotspotActiveSessions->isEmpty())
            <p class="text-sm text-zinc-500">{{ __('No rows stored yet — run a sync from repair actions or the scheduled/console command.') }}</p>
        @else
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-full text-xs">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-start">{{ __('MAC') }}</th>
                            <th class="px-3 py-2 text-start">{{ __('IP') }}</th>
                            <th class="px-3 py-2 text-start">{{ __('User') }}</th>
                            <th class="px-3 py-2 text-end">{{ __('Bytes in') }}</th>
                            <th class="px-3 py-2 text-end">{{ __('Bytes out') }}</th>
                            <th class="px-3 py-2 text-start">{{ __('Synced at') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->storedHotspotActiveSessions as $s)
                            <tr>
                                <td class="px-3 py-2 font-mono">{{ $s->mac_address }}</td>
                                <td class="px-3 py-2 font-mono">{{ $s->ip_address ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $s->user_name ?? '—' }}</td>
                                <td class="px-3 py-2 text-end font-mono">{{ number_format($s->bytes_in) }}</td>
                                <td class="px-3 py-2 text-end font-mono">{{ number_format($s->bytes_out) }}</td>
                                <td class="px-3 py-2 text-zinc-500">{{ $s->synced_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div>
        <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">{{ __('Onboarding timeline') }}</h2>
        <ul class="text-sm space-y-1 text-zinc-600 dark:text-zinc-400">
            <li>{{ __('Claimed') }}: {{ $router->claimed_at?->toDateTimeString() ?? $router->created_at?->toDateTimeString() ?? '—' }}</li>
            <li>{{ __('Script generated') }}: {{ $router->script_generated_at?->toDateTimeString() ?? '—' }}</li>
            <li>{{ __('Script downloaded') }}: {{ $router->script_downloaded_at?->toDateTimeString() ?? '—' }}</li>
            <li>{{ __('Script applied') }}: {{ $router->script_applied_at?->toDateTimeString() ?? '—' }}</li>
            <li>{{ __('Ready at') }}: {{ $router->ready_at?->toDateTimeString() ?? '—' }}</li>
            <li>{{ __('Last error code') }}: {{ $router->last_error_code ?? '—' }}</li>
        </ul>
    </div>

    @can('router-operations.repair')
        <div>
            <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">{{ __('Repair actions') }}</h2>
            <p class="text-xs text-zinc-500 mb-3">{{ __('Logged to the activity log with your admin user.') }}</p>
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" wire:click="actionRecalculateHealth(false)" icon="arrow-path">{{ __('Recalculate health') }}</flux:button>
                <flux:button size="sm" wire:click="actionRecalculateHealth(true)" icon="wifi" variant="primary"
                    wire:confirm="{{ __('Live API + portal probe on router — continue?') }}">{{ __('Health + live probe') }}</flux:button>
                <flux:button size="sm" wire:click="actionVerifyOnboarding(false)" icon="clipboard-document-list">{{ __('Verify onboarding (dry)') }}</flux:button>
                <flux:button size="sm" wire:click="actionVerifyOnboarding(true)" icon="clipboard-document"
                    wire:confirm="{{ __('Live probe for verify — continue?') }}">{{ __('Verify + probe') }}</flux:button>
                <flux:button size="sm" wire:click="actionRegenerateScriptKeep" icon="command-line">{{ __('Regenerate full script') }}</flux:button>
                <flux:button size="sm" wire:click="actionRegenerateScriptRotate" icon="key" variant="filled"
                    wire:confirm="{{ __('Rotate ZTP password and regenerate script?') }}">{{ __('Script + rotate API pw') }}</flux:button>
                <flux:button size="sm" wire:click="actionRegenerateBundle" icon="folder-open">{{ __('Refresh bundle metadata') }}</flux:button>
                <flux:button size="sm" wire:click="actionSyncTunnel" icon="globe-europe-africa">{{ __('Re-check tunnel') }}</flux:button>
                <flux:button size="sm" wire:click="actionVerifyPortal" icon="wifi"
                    wire:confirm="{{ __('Connect to router and verify bundle files — continue?') }}">{{ __('Re-check portal on router') }}</flux:button>
                <flux:button size="sm" wire:click="actionSyncHotspotActiveSessions" icon="signal"
                    wire:confirm="{{ __('Poll router for active hotspot sessions — continue?') }}">{{ __('Sync hotspot sessions') }}</flux:button>
                <flux:button size="sm" wire:click="actionRotateCredentials" icon="shield-exclamation" variant="danger"
                    wire:confirm="{{ __('Issue new ZTP password? Customer must use new script.') }}">{{ __('Rotate API credentials') }}</flux:button>
                <flux:button size="sm" wire:click="actionMarkScriptReissued" icon="document-plus">{{ __('Mark script reissued') }}</flux:button>
                <flux:button size="sm" wire:click="actionMarkReOnboarding" icon="arrow-uturn-left" variant="danger"
                    wire:confirm="{{ __('Reset onboarding to claimed and clear health snapshot?') }}">{{ __('Mark re-onboarding') }}</flux:button>
            </div>
            @if($verifyReportJson)
                <pre class="mt-4 text-xs bg-zinc-950 text-green-400 p-4 rounded-xl overflow-x-auto max-h-64">{{ $verifyReportJson }}</pre>
            @endif
        </div>
    @else
        <flux:callout variant="warning" icon="exclamation-triangle">{{ __('You do not have permission to run repair actions.') }}</flux:callout>
    @endcan

    <div>
        <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 mb-3">{{ __('Recent activity (this router)') }}</h2>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column>{{ __('By') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($this->recentActivityLogs() as $log)
                    <flux:table.row :key="$log->id">
                        <flux:table.cell class="text-xs text-zinc-500">{{ $log->created_at->diffForHumans() }}</flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $log->description }}</flux:table.cell>
                        <flux:table.cell class="text-xs">{{ $log->causer?->name ?? __('System') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-zinc-500 text-center py-6">{{ __('No logged events yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showScriptModal" class="w-full max-w-4xl">
        <div class="space-y-3">
            <flux:heading size="lg">{{ __('Generated setup script') }}</flux:heading>
            <pre class="text-xs bg-zinc-950 text-green-400 p-4 rounded-xl max-h-[60vh] overflow-auto whitespace-pre-wrap break-all">{{ $generatedScript }}</pre>
            <flux:button wire:click="$set('showScriptModal', false)">{{ __('Close') }}</flux:button>
        </div>
    </flux:modal>
</div>
