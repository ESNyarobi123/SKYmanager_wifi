<div
    class=""
    x-data="{
        copyScript(text) {
            navigator.clipboard.writeText(text).then(() => {
                $wire.scriptCopied = true;
                setTimeout(() => { $wire.scriptCopied = false; }, 3000);
            });
        }
    }"
>
    {{-- Toast notification --}}
    <div
        x-data="{ show: false, message: '', type: 'success' }"
        x-on:notify.window="show = true; message = $event.detail.message; type = $event.detail.type; setTimeout(() => show = false, 4000)"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-xl border px-4 py-3 shadow-xl"
        :class="type === 'success' ? 'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300' : 'bg-red-50 border-red-200 text-red-800'"
        style="display: none;"
    >
        <flux:icon name="check-circle" class="h-5 w-5 flex-shrink-0" />
        <span class="text-sm font-medium" x-text="message"></span>
    </div>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">{{ __('My Routers') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-neutral-500">{{ __('Manage your MikroTik routers') }}</p>
        </div>
        <flux:button :href="route('customer.routers.claim')" variant="primary" size="sm" wire:navigate>
            <x-lucide name="plus-circle" class="size-3.5 me-1.5"/>
            {{ __('Add Router') }}
        </flux:button>
    </div>

    @if($this->routers->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 dark:border-neutral-700 py-20 text-center mb-6">
            <div class="inline-flex items-center justify-center size-16 rounded-2xl bg-gray-100 dark:bg-neutral-800 mb-4">
                <x-lucide name="server" class="size-8 text-gray-400 dark:text-neutral-500"/>
            </div>
            <h3 class="text-lg font-semibold text-gray-700 dark:text-neutral-300">{{ __('No routers yet') }}</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-neutral-400 max-w-xs">
                {{ __('Add your first MikroTik router to start managing your WiFi network from here.') }}
            </p>
            <flux:button :href="route('customer.routers.claim')" variant="primary" class="mt-6" wire:navigate>
                <x-lucide name="plus-circle" class="size-4 me-1.5"/>
                {{ __('Add Your First Router') }}
            </flux:button>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 mb-6">
            @foreach($this->routers as $router)
                <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 overflow-hidden hover:shadow-md transition-shadow">
                    {{-- Card Header --}}
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-neutral-700">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <div class="inline-flex items-center justify-center size-10 rounded-xl bg-sky-100 dark:bg-sky-800/30">
                                    <x-lucide name="wifi" class="size-5 text-sky-600 dark:text-sky-400"/>
                                </div>
                                <span class="absolute -bottom-0.5 -right-0.5 size-3 rounded-full border-2 border-white dark:border-neutral-800 {{ $router->is_online ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-neutral-500' }}"></span>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800 dark:text-neutral-200 text-sm">{{ $router->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-neutral-400">{{ $router->hotspot_ssid }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <flux:badge
                                color="{{ $router->is_online ? 'green' : 'zinc' }}"
                                size="sm"
                            >
                                {{ $router->is_online ? __('Online') : __('Offline') }}
                            </flux:badge>
                            <flux:badge
                                color="{{ $this->onboardingBadgeVariant($router->onboarding_status) }}"
                                size="sm"
                                title="{{ $router->last_error_message }}"
                            >
                                {{ \App\Support\RouterOnboarding::label($router->onboarding_status) }}
                            </flux:badge>
                        </div>
                    </div>

                    {{-- Card Body --}}
                    <div class="flex-1 px-5 py-4 space-y-3">
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-xs text-gray-400 dark:text-neutral-500">{{ __('IP Address') }}</p>
                                <p class="font-medium text-gray-700 dark:text-neutral-300 font-mono text-xs mt-0.5">{{ $router->ip_address ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 dark:text-neutral-500">{{ __('WireGuard') }}</p>
                                <p class="font-medium font-mono text-xs mt-0.5">
                                    @if($router->vpn_connected)
                                        <span class="text-emerald-600 dark:text-emerald-400">{{ __('Connected') }}</span>
                                    @else
                                        <span class="text-gray-400 dark:text-neutral-500">{{ __('Not connected') }}</span>
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 dark:text-neutral-500">{{ __('Last Seen') }}</p>
                                <p class="font-medium text-gray-700 dark:text-neutral-300 text-xs mt-0.5">
                                    {{ $router->last_seen ? $router->last_seen->diffForHumans() : '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 dark:text-neutral-500">{{ __('Active Plan') }}</p>
                                <p class="font-medium text-gray-700 dark:text-neutral-300 text-xs mt-0.5">
                                    {{ $router->subscriptions->firstWhere('status', 'active')?->plan?->name ?? '—' }}
                                </p>
                            </div>
                        </div>

                        @if($router->mac_address)
                            <div>
                                <p class="text-xs text-gray-400 dark:text-neutral-500">{{ __('MAC Address') }}</p>
                                <p class="font-mono text-xs text-gray-600 dark:text-neutral-400 mt-0.5">{{ $router->mac_address }}</p>
                            </div>
                        @endif
                        @if($router->last_error_message && in_array($router->onboarding_status, ['error', 'cred_mismatch', 'offline', 'bundle_mismatch'], true))
                            <div class="rounded-lg bg-red-50 dark:bg-red-950/30 border border-red-100 dark:border-red-900/50 px-3 py-2">
                                <p class="text-[10px] font-semibold text-red-700 dark:text-red-300 uppercase tracking-wide">{{ __('Last issue') }}</p>
                                <p class="text-xs text-red-800 dark:text-red-200 mt-1 line-clamp-3">{{ $router->last_error_message }}</p>
                            </div>
                        @endif

                        @php
                            $healthOverall = $router->health_snapshot['overall'] ?? null;
                        @endphp
                        @if($healthOverall)
                            <div class="flex flex-wrap gap-1.5 text-[10px] text-zinc-600 dark:text-zinc-400">
                                <span class="font-medium text-zinc-500 dark:text-zinc-500">{{ __('Health') }}:</span>
                                <span class="rounded bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 font-mono uppercase">{{ $healthOverall }}</span>
                                @if($router->health_evaluated_at)
                                    <span class="text-zinc-400">· {{ $router->health_evaluated_at->diffForHumans() }}</span>
                                @endif
                            </div>
                        @endif

                        <div class="rounded-lg bg-sky-50/80 dark:bg-sky-900/10 border border-sky-100 dark:border-sky-900/40 px-3 py-2">
                            <p class="text-[10px] font-semibold text-sky-700 dark:text-sky-300 uppercase tracking-wide">{{ __('Portal bundle') }}</p>
                            <p class="text-xs text-gray-700 dark:text-neutral-300 mt-1">
                                {{ __('Version') }}: <span class="font-mono">{{ $router->portal_bundle_version ?? '—' }}</span>
                                @if($router->portal_bundle_hash)
                                    <span class="text-gray-400 dark:text-neutral-500"> · </span>
                                    <span class="font-mono text-[10px] text-gray-500 dark:text-neutral-400" title="{{ $router->portal_bundle_hash }}">{{ \Illuminate\Support\Str::limit($router->portal_bundle_hash, 14, '…') }}</span>
                                @endif
                            </p>
                            @if(config('skymanager.portal_bundle_version') && (string)($router->portal_bundle_version ?? '') !== (string)config('skymanager.portal_bundle_version'))
                                <p class="text-[10px] text-amber-700 dark:text-amber-400 mt-1">{{ __('App has a newer bundle version — regenerate script after refreshing bundle.') }}</p>
                            @endif
                        </div>

                        <div class="rounded-lg border border-zinc-100 dark:border-neutral-700 bg-zinc-50/50 dark:bg-neutral-900/30 px-3 py-2 space-y-1">
                            <p class="text-[10px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">{{ __('Readiness (at a glance)') }}</p>
                            <ul class="text-xs text-zinc-600 dark:text-zinc-300 space-y-0.5">
                                <li class="flex gap-1.5">
                                    <span class="text-zinc-400">·</span>
                                    {{ $router->onboarding_status === \App\Support\RouterOnboarding::READY ? __('Payments / hotspot: ready') : __('Payments / hotspot: not fully ready yet') }}
                                </li>
                                <li class="flex gap-1.5">
                                    <span class="text-zinc-400">·</span>
                                    {{ ($router->bundle_deployment_mode === 'bundle' && $router->portal_bundle_hash) ? __('Captive portal: bundle mode configured in SKYmanager') : __('Captive portal: finish bundle + script on router') }}
                                </li>
                                <li class="flex gap-1.5">
                                    <span class="text-zinc-400">·</span>
                                    {{ ($router->vpn_connected || $router->last_tunnel_ok) ? __('Tunnel: last check OK') : __('Tunnel: not verified or down') }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    {{-- Card Actions --}}
                    <div class="px-5 py-3 bg-gray-50 dark:bg-neutral-700/30 border-t border-gray-100 dark:border-neutral-700 flex flex-wrap gap-2">
                        <flux:button :href="route('customer.client-sessions').'?router='.urlencode($router->id)" variant="ghost" size="sm" icon="users" class="flex-1" wire:navigate>
                            {{ __('Clients') }}
                        </flux:button>
                        <flux:button :href="route('customer.plans.hotspot-bundle', ['routerId' => $router->id])" variant="ghost" size="sm" icon="folder-open" class="flex-1" wire:navigate>
                            {{ __('Bundle') }}
                        </flux:button>
                        <flux:button wire:click="regeneratePortalBundle('{{ $router->id }}')" variant="ghost" size="sm" icon="arrow-path" class="flex-1">
                            {{ __('Refresh bundle') }}
                        </flux:button>
                        <flux:button wire:click="viewRouter('{{ $router->id }}')" variant="ghost" size="sm" icon="eye" class="flex-1">
                            {{ __('Details') }}
                        </flux:button>
                        <flux:button wire:click="openScriptModal('{{ $router->id }}')" variant="ghost" size="sm" icon="code-bracket" class="flex-1 text-sky-600 dark:text-sky-400 border-sky-200 dark:border-sky-800 hover:bg-sky-50 dark:hover:bg-sky-900/20">
                            {{ __('Setup Script') }}
                        </flux:button>
                        <flux:button wire:click="openRenameModal('{{ $router->id }}')" variant="ghost" size="sm" icon="pencil" class="flex-1">
                            {{ __('Rename') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Detail Modal --}}
    <flux:modal wire:model="showDetailModal" name="router-detail" class="w-full max-w-xl">
        @if($this->selectedRouter)
            <div class="space-y-5 p-1">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-sky-100 dark:bg-sky-900/40">
                        <flux:icon name="server" class="h-6 w-6 text-sky-600 dark:text-sky-400" />
                    </div>
                    <div>
                        <flux:heading size="lg">{{ $this->selectedRouter->name }}</flux:heading>
                        <flux:text class="text-zinc-500">{{ $this->selectedRouter->hotspot_ssid }}</flux:text>
                    </div>
                    <div class="ml-auto">
                        <flux:badge color="{{ $this->selectedRouter->is_online ? 'green' : 'zinc' }}">
                            {{ $this->selectedRouter->is_online ? __('Online') : __('Offline') }}
                        </flux:badge>
                    </div>
                </div>

                <flux:separator />

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider">{{ __('IP Address') }}</p>
                        <p class="mt-1 font-mono text-zinc-800 dark:text-zinc-200">{{ $this->selectedRouter->ip_address }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider">{{ __('WG Address') }}</p>
                        <p class="mt-1 font-mono text-zinc-800 dark:text-zinc-200">{{ $this->selectedRouter->wg_address ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider">{{ __('SSID') }}</p>
                        <p class="mt-1 text-zinc-800 dark:text-zinc-200">{{ $this->selectedRouter->hotspot_ssid }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider">{{ __('Gateway') }}</p>
                        <p class="mt-1 font-mono text-zinc-800 dark:text-zinc-200">{{ $this->selectedRouter->hotspot_gateway }}</p>
                    </div>
                    @if($this->selectedRouter->mac_address)
                        <div class="col-span-2">
                            <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider">{{ __('MAC Address') }}</p>
                            <p class="mt-1 font-mono text-zinc-800 dark:text-zinc-200">{{ $this->selectedRouter->mac_address }}</p>
                        </div>
                    @endif
                </div>

                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/80 dark:bg-zinc-900/20 px-4 py-3 text-sm space-y-2">
                    <p class="text-xs font-medium text-zinc-700 dark:text-zinc-200">{{ __('Onboarding') }}</p>
                    <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ \App\Support\RouterOnboarding::label($this->selectedRouter->onboarding_status) }}</p>
                    @if($this->selectedRouter->last_error_message)
                        <p class="text-xs text-red-700 dark:text-red-300 whitespace-pre-wrap">{{ $this->selectedRouter->last_error_message }}</p>
                    @endif
                    @if($this->selectedRouter->onboarding_warnings && count($this->selectedRouter->onboarding_warnings))
                        <p class="text-[10px] font-medium text-zinc-500 uppercase">{{ __('Notes') }}</p>
                        <ul class="text-xs text-zinc-600 dark:text-zinc-400 list-disc ps-4 space-y-0.5">
                            @foreach(\Illuminate\Support\Arr::flatten($this->selectedRouter->onboarding_warnings) as $note)
                                @if(is_string($note) && $note !== '')
                                    <li>{{ $note }}</li>
                                @endif
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="rounded-xl border border-sky-100 dark:border-sky-900/40 bg-sky-50/50 dark:bg-sky-900/10 px-4 py-3 text-sm space-y-2">
                    <p class="text-xs font-medium text-sky-800 dark:text-sky-200">{{ __('Hotspot bundle (MikroTik)') }}</p>
                    <p class="text-xs text-zinc-600 dark:text-zinc-400 font-mono break-all">{{ __('Folder') }}: {{ $this->selectedRouter->portal_folder_name ?? '—' }}</p>
                    <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ __('Hash') }}: {{ \Illuminate\Support\Str::limit($this->selectedRouter->portal_bundle_hash ?? '—', 48, '…') }}</p>
                    <flux:button :href="route('customer.plans.hotspot-bundle', ['routerId' => $this->selectedRouter->id])" size="sm" variant="ghost" wire:navigate icon="folder-open">
                        {{ __('Inspect / preview bundle') }}
                    </flux:button>
                </div>

                <flux:button :href="route('customer.client-sessions').'?router='.urlencode($this->selectedRouter->id)" variant="primary" size="sm" wire:navigate icon="users" class="w-full">
                    {{ __('View client sessions for this router') }}
                </flux:button>

                @if($this->selectedRouter->subscriptions->isNotEmpty())
                    <flux:separator />
                    <div>
                        <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider mb-3">{{ __('Subscriptions') }}</p>
                        <div class="space-y-2">
                            @foreach($this->selectedRouter->subscriptions->take(5) as $sub)
                                <div class="flex items-center justify-between rounded-lg bg-zinc-50 dark:bg-zinc-800 px-3 py-2">
                                    <div>
                                        <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $sub->plan->name ?? '—' }}</p>
                                        <p class="text-xs text-zinc-500">{{ __('Expires') }}: {{ $sub->expires_at?->format('d M Y H:i') ?? '—' }}</p>
                                    </div>
                                    <flux:badge color="{{ $sub->status === 'active' ? 'green' : 'zinc' }}" size="sm">
                                        {{ ucfirst($sub->status) }}
                                    </flux:badge>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex gap-2 justify-end">
                    <flux:button wire:click="openScriptModal('{{ $this->selectedRouter->id }}')" variant="primary" size="sm" icon="code-bracket">
                        {{ __('Generate Setup Script') }}
                    </flux:button>
                    <flux:button wire:click="closeDetailModal" variant="ghost">{{ __('Close') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- ── Setup Script Modal ──────────────────────────────────────────── --}}
    <flux:modal wire:model="showScriptModal" name="setup-script" class="w-full max-w-3xl">
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-sky-100 dark:bg-sky-900/40">
                    <flux:icon name="code-bracket" class="h-5 w-5 text-sky-600 dark:text-sky-400" />
                </div>
                <div>
                    <flux:heading size="lg">{{ __('MikroTik Setup Script') }}</flux:heading>
                    <flux:text class="text-zinc-500 text-sm">{{ __('Paste this in your MikroTik terminal. It is safe to run multiple times.') }}</flux:text>
                </div>
            </div>

            <div class="rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 flex items-start gap-2">
                <flux:icon name="exclamation-triangle" class="h-4 w-4 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" />
                <p class="text-xs text-amber-700 dark:text-amber-400">
                    {{ __('Open MikroTik Winbox or WebFig → New Terminal → paste the full script and press Enter. The script downloads a full hotspot folder (login, rlogin, md5.js, …) for reliable captive-portal popups.') }}
                </p>
            </div>

            <div class="relative rounded-xl bg-zinc-950 dark:bg-zinc-900 border border-zinc-800 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2 bg-zinc-900 dark:bg-zinc-800 border-b border-zinc-800">
                    <div class="flex gap-1.5">
                        <span class="h-3 w-3 rounded-full bg-red-500/70"></span>
                        <span class="h-3 w-3 rounded-full bg-yellow-500/70"></span>
                        <span class="h-3 w-3 rounded-full bg-green-500/70"></span>
                    </div>
                    <span class="text-xs text-zinc-500 font-mono">RouterOS Terminal</span>
                    <button
                        x-on:click="copyScript($wire.generatedScript)"
                        class="flex items-center gap-1.5 rounded-md bg-zinc-700 hover:bg-zinc-600 px-3 py-1 text-xs font-medium text-zinc-200 transition-colors"
                    >
                        <template x-if="!$wire.scriptCopied">
                            <span class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                {{ __('Copy Script') }}
                            </span>
                        </template>
                        <template x-if="$wire.scriptCopied">
                            <span class="flex items-center gap-1.5 text-green-400">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                {{ __('Copied!') }}
                            </span>
                        </template>
                    </button>
                </div>
                <div class="max-h-96 overflow-y-auto p-4">
                    <pre class="text-xs font-mono text-green-400 leading-relaxed whitespace-pre-wrap break-all">{{ $generatedScript }}</pre>
                </div>
            </div>

            @if($this->selectedRouterId)
                <div class="rounded-lg border border-amber-200 dark:border-amber-900/50 bg-amber-50/80 dark:bg-amber-950/20 px-3 py-2 text-xs text-amber-900 dark:text-amber-200">
                    {{ __('If the router no longer accepts API login, use “New API password” then paste the new script once. Old passwords are not rotated unless you ask.') }}
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button wire:click="openScriptModalWithNewApiPassword('{{ $this->selectedRouterId }}')" variant="ghost" size="sm" icon="key">
                        {{ __('Regenerate with new API password') }}
                    </flux:button>
                </div>
            @endif

            <div class="flex flex-col gap-2 sm:flex-row sm:justify-between sm:items-center pt-2">
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('After pasting, wait for VPN and API checks. “Ready” appears only after the platform verifies your router — not immediately after claim.') }}
                </p>
                <div class="flex gap-2">
                    <flux:button wire:click="$set('showScriptModal', false)" variant="ghost" size="sm">{{ __('Close') }}</flux:button>
                    <flux:button wire:click="markScriptPasted" variant="primary" size="sm" icon="check">
                        {{ __('I Have Pasted It') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    {{-- ── Rename Modal ─────────────────────────────────────────────────── --}}
    <flux:modal wire:model="showRenameModal" name="rename-router" class="w-full max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Rename Router') }}</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">{{ __('Update the display name for this router.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('New Router Name') }}</flux:label>
                <flux:input
                    wire:model="newName"
                    type="text"
                    placeholder="{{ __('e.g. Dar Office, Mwanza Branch') }}"
                    autofocus
                />
                <flux:error name="newName" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-1">
                <flux:button wire:click="$set('showRenameModal', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button wire:click="renameRouter" variant="primary" icon="check">{{ __('Save Name') }}</flux:button>
            </div>
        </div>
    </flux:modal>

</div>
