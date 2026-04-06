<div>

    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">{{ __('Add / Claim a Router') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-neutral-500">
            {{ __('Register a new MikroTik router under your account.') }}
        </p>
    </div>

    @if($claimed)
        {{-- Success State --}}
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-8 text-center">
            <div class="mx-auto mb-4 inline-flex size-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-800/30">
                <x-lucide name="check-circle" class="size-8 text-emerald-600 dark:text-emerald-400"/>
            </div>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">{{ __('Router Added!') }}</h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-neutral-400">{{ $successMessage }}</p>
            <div class="mt-6 flex justify-center gap-3">
                <flux:button :href="route('customer.routers')" variant="primary" wire:navigate>
                    {{ __('View My Routers') }}
                </flux:button>
                <flux:button wire:click="resetForm" variant="ghost">
                    {{ __('Add Another') }}
                </flux:button>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- Form --}}
            <div class="lg:col-span-2">
                <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-neutral-700">
                        <h2 class="text-sm font-semibold text-gray-800 dark:text-neutral-200 flex items-center gap-2">
                            <x-lucide name="server" class="size-4 text-sky-500"/>
                            {{ __('Router Details') }}
                        </h2>
                    </div>
                    <form wire:submit="claimRouter" class="px-6 py-5 space-y-5">

                        <flux:field>
                            <flux:label>{{ __('Router Name') }} <span class="text-red-400">*</span></flux:label>
                            <flux:input
                                wire:model="name"
                                type="text"
                                placeholder="{{ __('e.g. Dar Office Router, Mwanza Branch') }}"
                                autofocus
                                required
                            />
                            <flux:error name="name" />
                        </flux:field>

                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>{{ __('MAC Address') }}</flux:label>
                                <flux:input
                                    wire:model="mac_address"
                                    type="text"
                                    placeholder="AA:BB:CC:DD:EE:FF"
                                    class="font-mono"
                                />
                                <flux:description>{{ __('Optional — found on router label') }}</flux:description>
                                <flux:error name="mac_address" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Router IP (before VPN)') }}</flux:label>
                                <flux:input
                                    wire:model="ip_address"
                                    type="text"
                                    placeholder="192.168.88.1"
                                    class="font-mono"
                                />
                                <flux:description>{{ __('Optional — LAN or public IP you use to reach the router before WireGuard is up (not the WG tunnel IP).') }}</flux:description>
                                <flux:error name="ip_address" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>{{ __('WiFi Network Name (SSID)') }}</flux:label>
                            <flux:input
                                wire:model="hotspot_ssid"
                                type="text"
                                placeholder="{{ __('e.g. PEACE WiFi, Office Net') }}"
                            />
                            <flux:description>{{ __('The name customers see when connecting') }}</flux:description>
                            <flux:error name="hotspot_ssid" />
                        </flux:field>

                        <div class="rounded-lg border border-zinc-200 dark:border-neutral-600 bg-zinc-50/80 dark:bg-neutral-800/40">
                            <button
                                type="button"
                                wire:click="$toggle('showAdvanced')"
                                class="flex w-full items-center justify-between gap-2 px-4 py-3 text-left text-sm font-medium text-zinc-800 dark:text-zinc-200"
                            >
                                <span class="flex items-center gap-2">
                                    <x-lucide name="sliders-horizontal" class="size-4 text-sky-600 dark:text-sky-400 shrink-0"/>
                                    {{ __('Advanced settings') }}
                                </span>
                                <x-lucide name="chevron-down" class="size-4 text-zinc-400 transition-transform {{ $showAdvanced ? 'rotate-180' : '' }}"/>
                            </button>

                            @if($showAdvanced)
                                <div class="space-y-4 border-t border-zinc-200 dark:border-neutral-600 px-4 pb-4 pt-3">
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Optional. Leave blank to use safe defaults — we will show reminders on your router card if anything was assumed.') }}
                                    </p>

                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <flux:field>
                                            <flux:label>{{ __('WAN interface') }}</flux:label>
                                            <flux:input wire:model="wan_interface" type="text" placeholder="ether1" class="font-mono"/>
                                            <flux:error name="wan_interface" />
                                        </flux:field>
                                        <flux:field>
                                            <flux:label>{{ __('WiFi interface') }}</flux:label>
                                            <flux:input wire:model="wifi_interface" type="text" placeholder="wlan1" class="font-mono"/>
                                            <flux:error name="wifi_interface" />
                                        </flux:field>
                                    </div>

                                    <flux:checkbox
                                        wire:model.live="use_default_network_settings"
                                        :label="__('Use default MikroTik LAN (192.168.88.x, bridge)')"
                                    />

                                    @if(! $use_default_network_settings)
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <flux:field>
                                                <flux:label>{{ __('Hotspot / bridge interface') }}</flux:label>
                                                <flux:input wire:model="hotspot_interface_custom" type="text" placeholder="bridge" class="font-mono"/>
                                                <flux:error name="hotspot_interface_custom" />
                                            </flux:field>
                                            <flux:field>
                                                <flux:label>{{ __('Hotspot gateway IP') }}</flux:label>
                                                <flux:input wire:model="hotspot_gateway_custom" type="text" placeholder="192.168.88.1" class="font-mono"/>
                                                <flux:error name="hotspot_gateway_custom" />
                                            </flux:field>
                                            <flux:field class="sm:col-span-2">
                                                <flux:label>{{ __('Hotspot network (CIDR)') }}</flux:label>
                                                <flux:input wire:model="hotspot_network_custom" type="text" placeholder="192.168.88.0/24" class="font-mono"/>
                                                <flux:error name="hotspot_network_custom" />
                                            </flux:field>
                                        </div>
                                    @endif

                                    <flux:field>
                                        <flux:label>{{ __('VPN mode') }}</flux:label>
                                        <flux:select wire:model.live="preferred_vpn_mode" class="w-full max-w-md">
                                            <flux:select.option value="wireguard">{{ __('WireGuard to SKYmanager (recommended)') }}</flux:select.option>
                                            <flux:select.option value="auto">{{ __('Auto — use WG only if server is fully configured') }}</flux:select.option>
                                            <flux:select.option value="none">{{ __('None — no VPN block in script') }}</flux:select.option>
                                        </flux:select>
                                        <flux:description>{{ __('Choose None only if you manage remote access yourself.') }}</flux:description>
                                        <flux:error name="preferred_vpn_mode" />
                                    </flux:field>

                                    @if($preferred_vpn_mode === 'wireguard')
                                        <div class="rounded-lg border border-sky-200/80 dark:border-sky-800 bg-sky-50/60 dark:bg-sky-950/20 p-4 space-y-4">
                                            <flux:field>
                                                <flux:label>{{ __('WireGuard tunnel IP (wg_address)') }}</flux:label>
                                                <flux:input
                                                    wire:model="wg_address"
                                                    type="text"
                                                    placeholder="10.10.0.5/32"
                                                    class="font-mono"
                                                    :disabled="$this->wireguardAutoAssignOffered && $wg_auto_assign"
                                                />
                                                <flux:description>
                                                    {{ __('Must be a free /32 inside WG_API_SUBNET on the server (ask your admin if unsure).') }}
                                                </flux:description>
                                                <flux:error name="wg_address" />
                                            </flux:field>
                                            @if($this->wireguardAutoAssignOffered)
                                                <flux:checkbox
                                                    wire:model.live="wg_auto_assign"
                                                    :label="__('Assign WireGuard IP automatically from pool')"
                                                />
                                                <flux:description>{{ __('Uses WG_AUTO_ASSIGN_IPS and WG_API_SUBNET — only when server WG env is complete.') }}</flux:description>
                                                <flux:error name="wg_auto_assign" />
                                            @endif
                                        </div>
                                    @endif

                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <flux:field>
                                            <flux:label>{{ __('Router model (optional)') }}</flux:label>
                                            <flux:input wire:model="router_model" type="text" placeholder="hAP ax³"/>
                                            <flux:error name="router_model" />
                                        </flux:field>
                                        <flux:field>
                                            <flux:label>{{ __('RouterOS version hint') }}</flux:label>
                                            <flux:input wire:model="routeros_version_hint" type="text" placeholder="7.16"/>
                                            <flux:error name="routeros_version_hint" />
                                        </flux:field>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <flux:field>
                                            <flux:label>{{ __('API username override') }}</flux:label>
                                            <flux:input wire:model="api_username_override" type="text" placeholder="sky-api"/>
                                            <flux:error name="api_username_override" />
                                        </flux:field>
                                        <flux:field>
                                            <flux:label>{{ __('API port') }}</flux:label>
                                            <flux:input wire:model="api_port_override" type="number" placeholder="8728"/>
                                            <flux:error name="api_port_override" />
                                        </flux:field>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="pt-2">
                            <flux:button type="submit" variant="primary" class="w-full sm:w-auto" icon="plus-circle">
                                {{ __('Add Router to My Account') }}
                            </flux:button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Help Panel --}}
            <div class="space-y-4">
                <div class="bg-sky-50 border border-sky-200 rounded-xl dark:bg-sky-900/10 dark:border-sky-800 p-5">
                    <div class="flex items-center gap-2 mb-3">
                        <x-lucide name="info" class="size-5 text-sky-600 dark:text-sky-400 shrink-0"/>
                        <h3 class="text-sm font-semibold text-sky-800 dark:text-sky-300">{{ __('How It Works') }}</h3>
                    </div>
                    <ol class="space-y-2 text-sm text-sky-700 dark:text-sky-400">
                        <li class="flex gap-2">
                            <span class="flex-shrink-0 flex h-5 w-5 items-center justify-center rounded-full bg-sky-200 dark:bg-sky-800 text-xs font-bold text-sky-800 dark:text-sky-300">1</span>
                            <span>{{ __('Enter your router name and optional details') }}</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="flex-shrink-0 flex h-5 w-5 items-center justify-center rounded-full bg-sky-200 dark:bg-sky-800 text-xs font-bold text-sky-800 dark:text-sky-300">2</span>
                            <span>{{ __('Router is linked to your account instantly') }}</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="flex-shrink-0 flex h-5 w-5 items-center justify-center rounded-full bg-sky-200 dark:bg-sky-800 text-xs font-bold text-sky-800 dark:text-sky-300">3</span>
                            <span>{{ __('Admin will provision your router with the setup script') }}</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="flex-shrink-0 flex h-5 w-5 items-center justify-center rounded-full bg-sky-200 dark:bg-sky-800 text-xs font-bold text-sky-800 dark:text-sky-300">4</span>
                            <span>{{ __('Your router appears online once VPN connects') }}</span>
                        </li>
                    </ol>
                </div>

                <div class="bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/10 dark:border-amber-800 p-5">
                    <div class="flex items-center gap-2 mb-2">
                        <x-lucide name="activity" class="size-5 text-amber-600 dark:text-amber-400 shrink-0"/>
                        <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-300">{{ __('Need Help?') }}</h3>
                    </div>
                    <p class="text-sm text-amber-700 dark:text-amber-400">
                        {{ __('Contact your SKYmanager admin to get the full RouterOS setup script for your device.') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

</div>
