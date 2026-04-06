<div>

    {{-- ── Page Header ─────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">{{ __('My Billing Plans') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-neutral-500">
                {{ __('Create and manage plans shown on your captive portal. Customers see only your plans.') }}
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0 flex-wrap">
            <flux:button size="sm" variant="ghost" icon="globe-alt" wire:click="$set('showPortalModal', true)">
                {{ __('My Portal URL') }}
            </flux:button>
            <flux:button size="sm" variant="ghost" icon="eye" wire:click="openPreviewModal" wire:loading.attr="disabled">
                {{ __('Preview') }}
            </flux:button>
            <flux:button size="sm" variant="primary" icon="folder-open" wire:click="openHotspotBundleFlow">
                {{ __('Hotspot bundle') }}
            </flux:button>
            <flux:button size="sm" variant="ghost" icon="arrow-down-tray" wire:click="openDownloadModal">
                {{ __('Legacy: one HTML file') }}
            </flux:button>
            <flux:button size="sm" variant="ghost" icon="plus" wire:click="openCreateModal">
                {{ __('Add Plan') }}
            </flux:button>
        </div>
    </div>

    {{-- ── Deploy Banner ──────────────────────────────────────────────────── --}}
    @if(!$this->plans->isEmpty())
    <div class="mb-6 rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-600 to-purple-600 dark:border-indigo-700 px-5 py-5 shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="inline-flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white/20 shadow-inner">
                    <x-lucide name="wifi" class="size-6 text-white"/>
                </div>
                <div class="min-w-0">
                    <p class="text-base font-bold text-white leading-tight">{{ __('Ready to deploy your hotspot?') }}</p>
                    <p class="text-xs text-indigo-200 mt-0.5 leading-relaxed">{{ __('Use the full hotspot bundle — same files your MikroTik setup script downloads (popup-safe).') }}</p>
                    <p class="text-xs text-indigo-300 mt-1">{{ __('Signed download links expire in 15 minutes for security.') }}</p>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 shrink-0">
                <div class="flex flex-col items-center gap-0.5">
                    <flux:button size="sm" icon="folder-open" wire:click="openHotspotBundleFlow"
                        class="w-full sm:w-auto bg-white text-indigo-700 hover:bg-indigo-50 border-0 font-bold shadow">
                        {{ __('Hotspot bundle') }}
                    </flux:button>
                    <span class="text-xs text-indigo-300">{{ __('Full folder structure') }}</span>
                </div>
                <div class="flex flex-col items-center gap-0.5">
                    <flux:button size="sm" icon="eye" wire:click="openPreviewModal" wire:loading.attr="disabled"
                        class="w-full sm:w-auto bg-white/15 hover:bg-white/25 border border-white/30 text-white font-semibold">
                        {{ __('Preview') }}
                    </flux:button>
                    <span class="text-xs text-indigo-300">{{ __('Opens in new tab') }}</span>
                </div>
                <div class="flex flex-col items-center gap-0.5">
                    <flux:button size="sm" variant="ghost" icon="arrow-down-tray" wire:click="openDownloadModal"
                        class="w-full sm:w-auto border border-white/40 text-white hover:bg-white/10 font-medium">
                        {{ __('Legacy one file') }}
                    </flux:button>
                    <span class="text-xs text-indigo-300/80">{{ __('Not recommended') }}</span>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Portal URL Banner (if subdomain already set) ───────────────────── --}}
    @if($this->customer->portal_subdomain)
    <div class="mb-6 flex items-center gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 dark:border-sky-800 dark:bg-sky-900/10">
        <x-lucide name="globe" class="size-4 text-sky-600 dark:text-sky-400 shrink-0"/>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-medium text-sky-700 dark:text-sky-300">{{ __('Your Captive Portal URL') }}</p>
            <p class="text-sm font-mono text-sky-800 dark:text-sky-200 truncate">{{ $this->portalUrl }}</p>
        </div>
        <flux:button size="xs" variant="ghost"
            x-data
            @click="navigator.clipboard.writeText('{{ $this->portalUrl }}').then(() => $wire.set('portalUrlCopied', true))">
            <x-lucide name="copy" class="size-3.5"/>
        </flux:button>
        @if($portalUrlCopied)
        <span class="text-xs text-emerald-600 dark:text-emerald-400">{{ __('Copied!') }}</span>
        @endif
    </div>
    @endif

    {{-- ── Plans Grid ──────────────────────────────────────────────────────── --}}
    @if($this->plans->isEmpty())
    <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 dark:border-neutral-700 py-16 text-center">
        <div class="inline-flex size-14 items-center justify-center rounded-full bg-sky-50 dark:bg-sky-900/20 mb-4">
            <x-lucide name="credit-card" class="size-7 text-sky-500"/>
        </div>
        <h3 class="text-sm font-semibold text-gray-700 dark:text-neutral-300">{{ __('No plans yet') }}</h3>
        <p class="mt-1 text-sm text-gray-400 dark:text-neutral-500 max-w-xs">
            {{ __('Create your first billing plan to display on your captive portal.') }}
        </p>
        <flux:button class="mt-4" size="sm" variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('Create First Plan') }}
        </flux:button>
    </div>
    @else
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($this->plans as $plan)
        <div class="flex flex-col rounded-xl border bg-white shadow-sm dark:bg-neutral-800 dark:border-neutral-700
            {{ $plan->is_active ? 'border-gray-200' : 'border-gray-100 opacity-60' }}">

            {{-- Card Header --}}
            <div class="flex items-start justify-between px-5 pt-5 pb-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-neutral-100 truncate">{{ $plan->name }}</h3>
                        @if(!$plan->is_active)
                        <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-neutral-700 px-2 py-0.5 text-[10px] font-medium text-gray-500 dark:text-neutral-400">
                            {{ __('Inactive') }}
                        </span>
                        @endif
                    </div>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                        TZS {{ number_format((float)$plan->price, 0) }}
                    </p>
                </div>
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" class="shrink-0"/>
                    <flux:menu>
                        <flux:menu.item icon="pencil" wire:click="openEditModal('{{ $plan->id }}')">{{ __('Edit') }}</flux:menu.item>
                        <flux:menu.item icon="{{ $plan->is_active ? 'eye-slash' : 'eye' }}" wire:click="toggleActive('{{ $plan->id }}')">
                            {{ $plan->is_active ? __('Deactivate') : __('Activate') }}
                        </flux:menu.item>
                        <flux:menu.separator/>
                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete('{{ $plan->id }}')">{{ __('Delete') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>

            {{-- Divider --}}
            <div class="mx-5 h-px bg-gray-100 dark:bg-neutral-700"></div>

            {{-- Stats --}}
            <div class="flex-1 px-5 py-4 space-y-2.5">
                <div class="flex items-center gap-2.5 text-sm text-gray-600 dark:text-neutral-400">
                    <x-lucide name="clock" class="size-3.5 shrink-0 text-sky-500"/>
                    <span>{{ $plan->durationLabel() }}</span>
                </div>
                <div class="flex items-center gap-2.5 text-sm text-gray-600 dark:text-neutral-400">
                    <x-lucide name="gauge" class="size-3.5 shrink-0 text-sky-500"/>
                    <span>{{ $plan->speedLabel() }}</span>
                </div>
                @if($plan->data_quota_mb)
                <div class="flex items-center gap-2.5 text-sm text-gray-600 dark:text-neutral-400">
                    <x-lucide name="database" class="size-3.5 shrink-0 text-sky-500"/>
                    <span>
                        @if($plan->data_quota_mb >= 1024)
                            {{ round($plan->data_quota_mb / 1024, 1) }} GB
                        @else
                            {{ $plan->data_quota_mb }} MB
                        @endif
                    </span>
                </div>
                @else
                <div class="flex items-center gap-2.5 text-sm text-gray-600 dark:text-neutral-400">
                    <x-lucide name="database" class="size-3.5 shrink-0 text-gray-300 dark:text-neutral-600"/>
                    <span class="text-gray-400 dark:text-neutral-600">{{ __('Unlimited data') }}</span>
                </div>
                @endif
                @if($plan->description)
                <p class="text-xs text-gray-400 dark:text-neutral-500 line-clamp-2 pt-1">{{ $plan->description }}</p>
                @endif
            </div>

        </div>
        @endforeach
    </div>
    @endif

    {{-- ══ CREATE / EDIT MODAL ══ --}}
    <flux:modal wire:model="showFormModal" class="w-full max-w-lg">
        <div class="mb-4">
            <flux:heading>{{ $editingPlanId ? __('Edit Plan') : __('New Billing Plan') }}</flux:heading>
            <flux:subheading>{{ __('Set the price, duration, speed limits, and data cap for this plan.') }}</flux:subheading>
        </div>

        <div class="space-y-4">

            {{-- Name --}}
            <flux:field>
                <flux:label>{{ __('Plan Name') }}</flux:label>
                <flux:input wire:model="name" placeholder="{{ __('e.g. 1 Hour Plan, Daily Unlimited') }}" autofocus/>
                <flux:error name="name"/>
            </flux:field>

            {{-- Price --}}
            <flux:field>
                <flux:label>{{ __('Price (TZS)') }}</flux:label>
                <flux:input wire:model="price" type="number" min="0" step="100" placeholder="500"/>
                <flux:error name="price"/>
            </flux:field>

            {{-- Duration --}}
            <div>
                <flux:label>{{ __('Duration') }}</flux:label>
                <div class="mt-1 flex gap-2">
                    <flux:input wire:model="durationMinutes" type="number" min="1"
                        placeholder="{{ match($durationUnit) { 'hours' => '1', 'days' => '1', default => '60' } }}"
                        class="flex-1"/>
                    <flux:select wire:model="durationUnit" class="w-32">
                        <flux:select.option value="minutes">{{ __('Minutes') }}</flux:select.option>
                        <flux:select.option value="hours">{{ __('Hours') }}</flux:select.option>
                        <flux:select.option value="days">{{ __('Days') }}</flux:select.option>
                    </flux:select>
                </div>
                <flux:error name="durationMinutes"/>
            </div>

            {{-- Speeds (Mbps — stored as kbps for MikroTik) --}}
            <div class="grid grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>{{ __('Upload (Mbps)') }}</flux:label>
                    <flux:input wire:model="uploadSpeedMbps" type="number" step="0.1" min="0.001" placeholder="{{ __('Unlimited') }}"/>
                    <flux:description>{{ __('Megabits per second. Leave blank for unlimited.') }}</flux:description>
                    <flux:error name="uploadSpeedMbps"/>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Download (Mbps)') }}</flux:label>
                    <flux:input wire:model="downloadSpeedMbps" type="number" step="0.1" min="0.001" placeholder="{{ __('Unlimited') }}"/>
                    <flux:description>{{ __('Megabits per second. Leave blank for unlimited.') }}</flux:description>
                    <flux:error name="downloadSpeedMbps"/>
                </flux:field>
            </div>

            {{-- Data Quota --}}
            <flux:field>
                <flux:label>{{ __('Data Quota (MB)') }}</flux:label>
                <flux:input wire:model="dataQuotaMb" type="number" min="1" placeholder="{{ __('Unlimited') }}"/>
                <flux:description>{{ __('Leave blank for unlimited. 1 GB = 1024 MB.') }}</flux:description>
                <flux:error name="dataQuotaMb"/>
            </flux:field>

            {{-- Description --}}
            <flux:field>
                <flux:label>{{ __('Description') }} <span class="text-zinc-400 font-normal">({{ __('optional') }})</span></flux:label>
                <flux:textarea wire:model="description" rows="2" placeholder="{{ __('Short description shown on the portal…') }}"/>
                <flux:error name="description"/>
            </flux:field>

            {{-- Active toggle --}}
            <div class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-neutral-700 px-4 py-3">
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-neutral-300">{{ __('Active') }}</p>
                    <p class="text-xs text-gray-400 dark:text-neutral-500">{{ __('Inactive plans are hidden from your portal') }}</p>
                </div>
                <flux:switch wire:model="isActive"/>
            </div>

        </div>

        <div class="flex justify-end gap-2 mt-6">
            <flux:button wire:click="$set('showFormModal', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
            <flux:button wire:click="savePlan" variant="primary" icon="check">
                {{ $editingPlanId ? __('Update Plan') : __('Create Plan') }}
            </flux:button>
        </div>
    </flux:modal>

    {{-- ══ DELETE CONFIRM MODAL ══ --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <div class="mb-4">
            <flux:heading>{{ __('Delete Plan?') }}</flux:heading>
            <flux:subheading>{{ __('This cannot be undone. The plan will be removed from your portal immediately.') }}</flux:subheading>
        </div>
        <div class="flex justify-end gap-2">
            <flux:button wire:click="$set('showDeleteModal', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
            <flux:button wire:click="deletePlan" variant="danger" icon="trash">{{ __('Delete') }}</flux:button>
        </div>
    </flux:modal>

    {{-- ══ PREVIEW IN BROWSER MODAL ══ --}}
    <flux:modal wire:model="showPreviewModal" class="w-full max-w-lg">
        <div class="mb-4">
            <flux:heading>{{ __('Preview Login Page') }}</flux:heading>
            <flux:subheading>{{ __('Select a router to preview its login page in a new browser tab. The preview link is valid for 30 minutes.') }}</flux:subheading>
        </div>

        <div class="space-y-2">
            @foreach($this->customerRouters as $router)
            <div class="flex items-center justify-between rounded-xl border border-gray-200 dark:border-neutral-700 px-4 py-3 hover:border-indigo-300 dark:hover:border-indigo-700 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-neutral-800">
                        <x-lucide name="wifi" class="size-4 text-gray-500 dark:text-neutral-400"/>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-800 dark:text-neutral-200 truncate">{{ $router->hotspot_ssid ?: $router->name }}</p>
                        @if($router->hotspot_ssid && $router->name !== $router->hotspot_ssid)
                        <p class="text-xs text-gray-400 dark:text-neutral-500 truncate">{{ $router->name }}</p>
                        @endif
                    </div>
                </div>
                <flux:button size="sm" variant="ghost" wire:click="previewForRouter('{{ $router->id }}')"
                    icon="arrow-top-right-on-square" wire:loading.attr="disabled">
                    {{ __('Open Preview') }}
                </flux:button>
            </div>
            @endforeach
        </div>

        <div class="flex justify-end mt-4">
            <flux:button wire:click="$set('showPreviewModal', false)" variant="ghost">{{ __('Close') }}</flux:button>
        </div>
    </flux:modal>

    {{-- ══ HOTSPOT BUNDLE (recommended) — pick router ══ --}}
    <flux:modal wire:model="showBundleRouterModal" class="w-full max-w-lg">
        <div class="mb-4">
            <flux:heading>{{ __('Hotspot bundle') }}</flux:heading>
            <flux:subheading>{{ __('Open the full portal package for a router — every file the captive portal needs, matching what the MikroTik setup script installs.') }}</flux:subheading>
        </div>

        <div class="space-y-2">
            @foreach($this->customerRouters as $router)
            <div class="flex items-center justify-between rounded-xl border border-gray-200 dark:border-neutral-700 px-4 py-3 hover:border-sky-300 dark:hover:border-sky-700 hover:bg-sky-50/50 dark:hover:bg-sky-900/10 transition-colors">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-neutral-800">
                        <x-lucide name="folder-open" class="size-4 text-gray-500 dark:text-neutral-400"/>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-800 dark:text-neutral-200 truncate">{{ $router->hotspot_ssid ?: $router->name }}</p>
                        @if($router->hotspot_ssid && $router->name !== $router->hotspot_ssid)
                        <p class="text-xs text-gray-400 dark:text-neutral-500 truncate">{{ $router->name }}</p>
                        @endif
                    </div>
                </div>
                <flux:button size="sm" variant="primary" wire:click="goToHotspotBundleForRouter('{{ $router->id }}')"
                    icon="arrow-right" wire:loading.attr="disabled">
                    {{ __('Open') }}
                </flux:button>
            </div>
            @endforeach
        </div>

        <div class="mt-4 rounded-lg bg-sky-50 dark:bg-sky-900/10 border border-sky-200 dark:border-sky-800 px-4 py-3">
            <p class="text-xs text-sky-800 dark:text-sky-200">
                {{ __('For automatic install, copy the setup script from My Routers — it downloads this same bundle into the correct hotspot folder on the router.') }}
            </p>
        </div>

        <div class="flex justify-end mt-4">
            <flux:button wire:click="$set('showBundleRouterModal', false)" variant="ghost">{{ __('Close') }}</flux:button>
        </div>
    </flux:modal>

    {{-- ══ LEGACY: single self-contained HTML (optional) ══ --}}
    <flux:modal wire:model="showDownloadModal" class="w-full max-w-lg">
        <div class="mb-4">
            <flux:heading>{{ __('Legacy: single HTML file') }}</flux:heading>
            <flux:subheading>{{ __('One old-style file with everything inlined. Prefer the hotspot bundle + setup script for reliable pop-ups and updates.') }}</flux:subheading>
        </div>

        <div class="space-y-2">
            @foreach($this->customerRouters as $router)
            <div class="flex items-center justify-between rounded-xl border border-gray-200 dark:border-neutral-700 px-4 py-3 hover:border-indigo-300 dark:hover:border-indigo-700 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-neutral-800">
                        <x-lucide name="wifi" class="size-4 text-gray-500 dark:text-neutral-400"/>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-800 dark:text-neutral-200 truncate">{{ $router->hotspot_ssid ?: $router->name }}</p>
                        @if($router->hotspot_ssid && $router->name !== $router->hotspot_ssid)
                        <p class="text-xs text-gray-400 dark:text-neutral-500 truncate">{{ $router->name }}</p>
                        @endif
                    </div>
                </div>
                <flux:button size="sm" variant="ghost" wire:click="downloadForRouter('{{ $router->id }}')"
                    icon="arrow-down-tray" wire:loading.attr="disabled">
                    {{ __('Download') }}
                </flux:button>
            </div>
            @endforeach
        </div>

        <div class="mt-5 rounded-lg bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 px-4 py-3">
            <p class="text-xs font-medium text-amber-800 dark:text-amber-200">
                {{ __('The file is named so you know it is the legacy build. If you upload it by hand, rename it to login.html inside the hotspot directory only — the recommended path is the bundle + script from My Routers.') }}
            </p>
        </div>

        <div class="flex justify-end mt-4">
            <flux:button wire:click="$set('showDownloadModal', false)" variant="ghost">{{ __('Close') }}</flux:button>
        </div>
    </flux:modal>

    {{-- ══ PORTAL URL MODAL ══ --}}
    <flux:modal wire:model="showPortalModal" class="w-full max-w-lg">
        <div class="mb-4">
            <flux:heading>{{ __('Your Captive Portal URL') }}</flux:heading>
            <flux:subheading>{{ __('This unique URL is embedded in your MikroTik setup script. Your hotspot customers see only your plans.') }}</flux:subheading>
        </div>

        <div class="space-y-4">

            {{-- URL display --}}
            <div class="rounded-xl bg-sky-50 dark:bg-sky-900/10 border border-sky-200 dark:border-sky-800 p-4">
                <p class="text-xs font-semibold text-sky-600 dark:text-sky-400 uppercase tracking-widest mb-2">{{ __('Portal URL') }}</p>
                <div class="flex items-center gap-2">
                    <p class="flex-1 font-mono text-sm text-sky-800 dark:text-sky-200 break-all">{{ $this->portalUrl }}</p>
                    <button
                        x-data
                        @click="navigator.clipboard.writeText('{{ $this->portalUrl }}').then(() => $wire.set('portalUrlCopied', true))"
                        class="shrink-0 p-1.5 rounded-lg text-sky-600 hover:bg-sky-100 dark:hover:bg-sky-800/30 transition">
                        <x-lucide name="{{ $portalUrlCopied ? 'check' : 'copy' }}" class="size-4"/>
                    </button>
                </div>
                @if($portalUrlCopied)
                <p class="mt-1.5 text-xs text-emerald-600 dark:text-emerald-400">{{ __('Copied to clipboard!') }}</p>
                @endif
            </div>

            {{-- How it works info --}}
            <div class="space-y-2.5">
                @foreach([
                    __('This URL is automatically embedded when you generate the MikroTik Setup Script.'),
                    __('When a hotspot user opens a browser, they land on YOUR portal showing YOUR plans.'),
                    __('Only your active plans are displayed — other customers\' plans are never shown.'),
                    __('Regenerating creates a new URL and you must re-generate the MikroTik script.'),
                ] as $tip)
                <div class="flex items-start gap-2.5 text-sm text-gray-600 dark:text-neutral-400">
                    <x-lucide name="check-circle" class="size-4 text-sky-500 shrink-0 mt-0.5"/>
                    <span>{{ $tip }}</span>
                </div>
                @endforeach
            </div>

            {{-- Regenerate warning --}}
            <div class="rounded-lg bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 px-4 py-3">
                <p class="text-xs font-medium text-amber-700 dark:text-amber-400">{{ __('⚠ Regenerating changes your URL — you must re-generate and re-apply the MikroTik script on all routers.') }}</p>
            </div>

        </div>

        <div class="flex justify-end gap-2 mt-2">
            <flux:button wire:click="$set('showPortalModal', false)" variant="ghost">{{ __('Close') }}</flux:button>
            <flux:button wire:click="regeneratePortalSubdomain" variant="ghost" icon="arrow-path"
                wire:confirm="{{ __('This will change your portal URL and break existing router scripts. Continue?') }}">
                {{ __('Regenerate URL') }}
            </flux:button>
        </div>
    </flux:modal>

    @script
    <script>
        $wire.on('open-preview-url', ({ url }) => {
            window.open(url, '_blank');
        });
    </script>
    @endscript

</div>
