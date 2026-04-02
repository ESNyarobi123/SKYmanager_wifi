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
                                <flux:label>{{ __('Router IP Address') }}</flux:label>
                                <flux:input
                                    wire:model="ip_address"
                                    type="text"
                                    placeholder="10.10.0.x"
                                    class="font-mono"
                                />
                                <flux:description>{{ __('WireGuard VPN IP (optional)') }}</flux:description>
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
