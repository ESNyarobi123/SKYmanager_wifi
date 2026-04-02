<div>

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">{{ __('Payment Settings') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-neutral-500">
            {{ __('Connect your own ClickPesa merchant account so payments from your hotspot customers go directly to you.') }}
        </p>
    </div>

    @if(session('gateway_disabled'))
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 dark:bg-amber-800/10 dark:border-amber-900 dark:text-amber-500 mb-6" role="alert">
            <div class="flex gap-x-3">
                <x-lucide name="activity" class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5"/>
                <p class="text-sm">{{ __('Your ClickPesa gateway has been disabled. Payments will now use the system fallback account.') }}</p>
            </div>
        </div>
    @endif

    {{-- ── Status Card ─────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-4 rounded-xl border p-5 mb-6
        {{ $this->gateway?->isConfigured() ? 'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/10 dark:border-emerald-800' : 'bg-gray-50 border-gray-200 dark:bg-neutral-800 dark:border-neutral-700' }}">
        <div class="inline-flex size-12 shrink-0 items-center justify-center rounded-xl
            {{ $this->gateway?->isConfigured() ? 'bg-emerald-100 dark:bg-emerald-800/30' : 'bg-gray-200 dark:bg-neutral-700' }}">
            @if($this->gateway?->isConfigured())
                <x-lucide name="check-circle" class="size-6 text-emerald-600 dark:text-emerald-400"/>
            @else
                <x-lucide name="x-circle" class="size-6 text-gray-400 dark:text-neutral-500"/>
            @endif
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold {{ $this->gateway?->isConfigured() ? 'text-emerald-800 dark:text-emerald-300' : 'text-gray-600 dark:text-neutral-400' }}">
                @if($this->gateway?->isConfigured())
                    {{ __('ClickPesa Connected') }}
                    @if($this->gateway->isVerified())
                        <span class="ml-2 inline-flex items-center gap-1 text-xs font-medium bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 px-2 py-0.5 rounded-full">
                            <x-lucide name="shield" class="size-3"/>
                            {{ __('Verified') }}
                        </span>
                    @else
                        <span class="ml-2 text-xs font-medium text-amber-600 dark:text-amber-400">({{ __('Not verified yet') }})</span>
                    @endif
                @else
                    {{ __('Not Configured') }}
                @endif
            </p>
            <p class="text-xs mt-0.5 {{ $this->gateway?->isConfigured() ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-400 dark:text-neutral-500' }}">
                @if($this->gateway?->isConfigured())
                    {{ __('Hotspot payments go directly to your ClickPesa account.') }}
                    @if($this->gateway->last_used_at)
                        &nbsp;·&nbsp; {{ __('Last used') }}: {{ $this->gateway->last_used_at->diffForHumans() }}
                    @endif
                @else
                    {{ __('Using system default account. Configure below to receive payments directly.') }}
                @endif
            </p>
        </div>
        @if($this->gateway?->isConfigured())
            <flux:button size="sm" variant="ghost"
                wire:click="disableGateway"
                wire:confirm="{{ __('Disable your ClickPesa connection? Payments will revert to the system account.') }}"
                class="text-red-500 hover:text-red-600 shrink-0">
                {{ __('Disable') }}
            </flux:button>
        @endif
    </div>

    {{-- ── Test Result ──────────────────────────────────────────────────────── --}}
    @if($testPassed === true)
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 dark:bg-emerald-800/10 dark:border-emerald-900 dark:text-emerald-500 mb-4" role="alert">
            <div class="flex gap-x-3">
                <x-lucide name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5"/>
                <p class="text-sm">{{ $testMessage }}</p>
            </div>
        </div>
    @elseif($testPassed === false)
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-4 dark:bg-red-800/10 dark:border-red-900 dark:text-red-500 mb-4" role="alert">
            <div class="flex gap-x-3">
                <x-lucide name="x-circle" class="size-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5"/>
                <p class="text-sm">{{ $testMessage }}</p>
            </div>
        </div>
    @endif

    @if($saved)
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 dark:bg-emerald-800/10 dark:border-emerald-900 dark:text-emerald-500 mb-4" role="alert">
            <div class="flex gap-x-3">
                <x-lucide name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5"/>
                <p class="text-sm">{{ __('Credentials saved successfully.') }}</p>
            </div>
        </div>
    @endif

    {{-- ── Form ─────────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 divide-y divide-gray-100 dark:divide-neutral-700 mb-6">

        {{-- Form header --}}
        <div class="px-6 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-800 dark:text-neutral-200 flex items-center gap-2">
                    <x-lucide name="key" class="size-4 text-sky-500"/>
                    {{ __('ClickPesa Credentials') }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-neutral-500 mt-0.5">
                    {{ __('Get these from your ClickPesa merchant dashboard → API Keys.') }}
                </p>
            </div>
            @if($this->gateway && !$keysEditable)
                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="enableEdit">
                    {{ __('Update Keys') }}
                </flux:button>
            @endif
        </div>

        <div class="px-6 py-5 space-y-4">
            {{-- Consumer Key --}}
            <flux:field>
                <flux:label>{{ __('Consumer Key (Client ID)') }}</flux:label>
                @if($this->gateway && !$keysEditable)
                    <div class="mt-1 rounded-lg bg-gray-50 dark:bg-neutral-700 border border-gray-200 dark:border-neutral-600 px-4 py-2.5 font-mono text-sm text-gray-600 dark:text-neutral-400">
                        {{ $this->gateway->maskedConsumerKey() }}
                    </div>
                    <flux:description>{{ __('Key is saved and encrypted. Click "Update Keys" to change it.') }}</flux:description>
                @else
                    <flux:input
                        wire:model="consumerKey"
                        type="password"
                        placeholder="{{ __('Paste your ClickPesa Client ID') }}"
                        autocomplete="off"
                    />
                    <flux:error name="consumerKey" />
                @endif
            </flux:field>

            {{-- Consumer Secret --}}
            <flux:field>
                <flux:label>{{ __('Consumer Secret (API Key)') }}</flux:label>
                @if($this->gateway && !$keysEditable)
                    <div class="mt-1 rounded-lg bg-gray-50 dark:bg-neutral-700 border border-gray-200 dark:border-neutral-600 px-4 py-2.5 font-mono text-sm text-gray-600 dark:text-neutral-400">
                        {{ $this->gateway->maskedConsumerSecret() }}
                    </div>
                    <flux:description>{{ __('Secret is saved and encrypted.') }}</flux:description>
                @else
                    <flux:input
                        wire:model="consumerSecret"
                        type="password"
                        placeholder="{{ __('Paste your ClickPesa API Key') }}"
                        autocomplete="off"
                    />
                    <flux:error name="consumerSecret" />
                @endif
            </flux:field>

            {{-- Account Number --}}
            <flux:field>
                <flux:label>{{ __('Account Number') }} <span class="text-zinc-400 font-normal">({{ __('optional') }})</span></flux:label>
                @if($this->gateway && !$keysEditable && $this->gateway->account_number)
                    <div class="mt-1 rounded-lg bg-gray-50 dark:bg-neutral-700 border border-gray-200 dark:border-neutral-600 px-4 py-2.5 font-mono text-sm text-gray-600 dark:text-neutral-400">
                        {{ $this->gateway->maskedAccountNumber() }}
                    </div>
                @else
                    <flux:input
                        wire:model="accountNumber"
                        placeholder="{{ __('e.g. 255XXXXXXXXX') }}"
                        autocomplete="off"
                    />
                    <flux:description>{{ __('Your registered ClickPesa merchant account number.') }}</flux:description>
                    <flux:error name="accountNumber" />
                @endif
            </flux:field>

            {{-- Security notice --}}
            <div class="flex items-start gap-3 rounded-lg bg-sky-50 dark:bg-sky-900/10 border border-sky-200 dark:border-sky-800 px-4 py-3">
                <x-lucide name="lock" class="size-4 text-sky-600 dark:text-sky-400 mt-0.5 shrink-0"/>
                <p class="text-xs text-sky-700 dark:text-sky-400 leading-relaxed">
                    {{ __('Your credentials are encrypted at rest using AES-256. They are never shown in full after saving. Only the last 6 characters are displayed for verification.') }}
                </p>
            </div>
        </div>

        {{-- Actions --}}
        <div class="px-6 py-4 flex flex-wrap items-center justify-between gap-3">
            <flux:button
                wire:click="testConnection"
                wire:loading.attr="disabled"
                variant="ghost"
                icon="signal"
                :disabled="!$this->gateway?->isConfigured() || $testing"
            >
                <span wire:loading.remove wire:target="testConnection">{{ __('Test Connection') }}</span>
                <span wire:loading wire:target="testConnection">{{ __('Testing…') }}</span>
            </flux:button>
            <div class="flex gap-2">
                @if($keysEditable)
                    <flux:button wire:click="$set('keysEditable', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
                @endif
                @if(!$this->gateway || $keysEditable)
                    <flux:button wire:click="save" variant="primary" icon="check">
                        {{ $this->gateway ? __('Update Credentials') : __('Save Credentials') }}
                    </flux:button>
                @elseif($this->gateway && !$this->gateway->isVerified())
                    <flux:button wire:click="testConnection" variant="primary" icon="signal">
                        {{ __('Verify Connection') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    {{-- ── How It Works ──────────────────────────────────────────────────────── --}}
    <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-6">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-neutral-200 flex items-center gap-2 mb-4">
            <x-lucide name="info" class="size-4 text-sky-500"/>
            {{ __('How It Works') }}
        </h2>
        <ol class="space-y-3">
            @foreach([
                __('A hotspot user on your router initiates a payment.'),
                __('SKYmanager detects which router processed the payment and looks up its owner (you).'),
                __('The USSD-push request is sent using your ClickPesa API credentials — money goes to your account.'),
                __('If you have not configured credentials, the system falls back to the platform default account.'),
            ] as $i => $step)
                <li class="flex items-start gap-3">
                    <span class="inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-sky-100 dark:bg-sky-800/30 text-xs font-bold text-sky-600 dark:text-sky-400">{{ $i + 1 }}</span>
                    <span class="text-sm text-gray-600 dark:text-neutral-400">{{ $step }}</span>
                </li>
            @endforeach
        </ol>
    </div>

</div>
