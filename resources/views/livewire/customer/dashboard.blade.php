<div>

    {{-- ══ PAGE HEADER ══ --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">
                {{ __('Welcome back') }}, {{ $this->customer->name }}!
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-neutral-500">
                {{ $this->customer->company_name ? $this->customer->company_name.' · ' : '' }}{{ $this->customer->phone }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($this->unreadNotificationCount > 0)
                <a href="{{ route('customer.notifications') }}" wire:navigate
                   class="relative inline-flex items-center justify-center size-9 rounded-lg border border-amber-200 bg-amber-50 text-amber-600 hover:bg-amber-100 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-400 transition-colors">
                    <x-lucide name="bell" class="size-4"/>
                    <span class="absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full bg-red-500 text-white text-[10px] font-bold">
                        {{ min($this->unreadNotificationCount, 9) }}{{ $this->unreadNotificationCount > 9 ? '+' : '' }}
                    </span>
                </a>
            @endif
            <flux:button :href="route('customer.routers.claim')" variant="primary" size="sm" wire:navigate>
                <x-lucide name="plus-circle" class="size-3.5 me-1.5"/>
                {{ __('Add Router') }}
            </flux:button>
        </div>
    </div>

    {{-- ══ EXPIRING ALERT — Preline callout ══ --}}
    @if($this->expiringSoon->isNotEmpty())
    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl p-4 dark:bg-yellow-800/10 dark:border-yellow-900 dark:text-yellow-500 mb-6" role="alert">
        <div class="flex gap-x-3">
            <x-lucide name="activity" class="size-5 text-yellow-600 dark:text-yellow-400 shrink-0 mt-0.5"/>
            <div class="grow">
                <h3 class="text-sm font-semibold">
                    {{ trans_choice('{1}1 subscription expires soon!|[2,*]:count subscriptions expire soon!', $this->expiringSoon->count(), ['count' => $this->expiringSoon->count()]) }}
                </h3>
                <div class="mt-1 space-y-0.5">
                    @foreach($this->expiringSoon as $sub)
                        <p class="text-xs">
                            <span class="font-medium">{{ $sub->plan->name ?? '—' }}</span>
                            on <span class="font-medium">{{ $sub->router->name ?? '—' }}</span>
                            — expires {{ $sub->expires_at->diffForHumans() }}
                        </p>
                    @endforeach
                </div>
            </div>
            <flux:button :href="route('customer.subscriptions')" variant="ghost" size="sm" wire:navigate class="shrink-0">
                {{ __('Renew') }}
            </flux:button>
        </div>
    </div>
    @endif

    {{-- ══ STAT CARDS ══ --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 mb-6">

        {{-- Total Routers --}}
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-sky-100 dark:bg-sky-800/30">
                    <x-lucide name="server" class="size-5 text-sky-600 dark:text-sky-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">{{ __('Total Routers') }}</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 dark:text-neutral-200">{{ $this->totalRouterCount }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-neutral-500">{{ $this->activeRouterCount }} online now</p>
        </div>

        {{-- Online --}}
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-emerald-100 dark:bg-emerald-800/30">
                    <x-lucide name="signal" class="size-5 text-emerald-600 dark:text-emerald-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">{{ __('Online Now') }}</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 dark:text-neutral-200">{{ $this->activeRouterCount }}</p>
            <div class="mt-1 flex items-center gap-1">
                <span class="flex size-2 rounded-full bg-emerald-500"></span>
                <p class="text-xs text-emerald-600 dark:text-emerald-400">Live</p>
            </div>
        </div>

        {{-- Active Plans --}}
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-violet-100 dark:bg-violet-800/30">
                    <x-lucide name="credit-card" class="size-5 text-violet-600 dark:text-violet-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">{{ __('Active Plans') }}</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 dark:text-neutral-200">{{ $this->activeSubscriptions->count() }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-neutral-500">{{ __('subscriptions') }}</p>
        </div>

        {{-- Monthly Spend --}}
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-teal-100 dark:bg-teal-800/30">
                    <x-lucide name="bar-chart-3" class="size-5 text-teal-600 dark:text-teal-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">{{ __('This Month') }}</span>
            </div>
            <p class="text-2xl font-bold text-gray-800 dark:text-neutral-200 leading-tight">TZS {{ number_format($this->monthlySpend) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-neutral-500">{{ now()->format('F Y') }}</p>
        </div>

    </div>

    {{-- ══ ROUTERS + SUBSCRIPTIONS GRID ══ --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 mb-6">

        {{-- My Routers card --}}
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-neutral-700">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-neutral-200">
                    <x-lucide name="server" class="size-4 text-sky-500"/>
                    {{ __('My Routers') }}
                </h2>
                <flux:button :href="route('customer.routers')" variant="ghost" size="sm" wire:navigate>
                    {{ __('View all') }}
                </flux:button>
            </div>

            @forelse($this->routers->take(5) as $router)
            <div class="flex items-center gap-3 px-5 py-3.5 {{ !$loop->last ? 'border-b border-gray-100 dark:border-neutral-700' : '' }} hover:bg-gray-50 dark:hover:bg-neutral-700/30 transition-colors">
                <div class="relative shrink-0">
                    <div class="inline-flex items-center justify-center size-9 rounded-lg bg-gray-100 dark:bg-neutral-700">
                        <x-lucide name="wifi" class="size-4 text-gray-500 dark:text-neutral-400"/>
                    </div>
                    <span class="absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full border-2 border-white dark:border-neutral-800 {{ $router->is_online ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-neutral-500' }}"></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 dark:text-neutral-200 truncate">{{ $router->name }}</p>
                    <p class="text-xs text-gray-500 dark:text-neutral-400 truncate">
                        {{ $router->hotspot_ssid ?? 'No SSID' }} ·
                        <span class="{{ $router->is_online ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                            {{ $router->is_online ? __('Online') : __('Offline') }}
                        </span>
                    </p>
                </div>
                @if($router->last_seen)
                <span class="text-xs text-gray-400 dark:text-neutral-500 shrink-0">{{ $router->last_seen->diffForHumans() }}</span>
                @endif
            </div>
            @empty
            <div class="flex flex-col items-center justify-center py-12 text-center px-5">
                <div class="inline-flex items-center justify-center size-12 rounded-xl bg-gray-100 dark:bg-neutral-700 mb-3">
                    <x-lucide name="server" class="size-6 text-gray-400 dark:text-neutral-500"/>
                </div>
                <p class="text-sm font-medium text-gray-600 dark:text-neutral-400">{{ __('No routers yet') }}</p>
                <p class="text-xs text-gray-400 dark:text-neutral-500 mt-1 mb-4">{{ __('Add your first router to get started') }}</p>
                <flux:button :href="route('customer.routers.claim')" variant="primary" size="sm" wire:navigate>
                    {{ __('Add Router') }}
                </flux:button>
            </div>
            @endforelse
        </div>

        {{-- Active Subscriptions card --}}
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-neutral-700">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-neutral-200">
                    <x-lucide name="credit-card" class="size-4 text-violet-500"/>
                    {{ __('Active Subscriptions') }}
                </h2>
                <flux:button :href="route('customer.subscriptions')" variant="ghost" size="sm" wire:navigate>
                    {{ __('View all') }}
                </flux:button>
            </div>

            @forelse($this->activeSubscriptions as $sub)
            <div class="flex items-center gap-3 px-5 py-3.5 {{ !$loop->last ? 'border-b border-gray-100 dark:border-neutral-700' : '' }} hover:bg-gray-50 dark:hover:bg-neutral-700/30 transition-colors">
                <div class="inline-flex items-center justify-center size-9 rounded-lg bg-violet-100 dark:bg-violet-800/30 shrink-0">
                    <x-lucide name="signal" class="size-4 text-violet-600 dark:text-violet-400"/>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 dark:text-neutral-200 truncate">{{ $sub->plan->name ?? 'Plan' }}</p>
                    <p class="text-xs text-gray-500 dark:text-neutral-400 truncate">{{ $sub->router->name ?? '–' }}</p>
                </div>
                <div class="shrink-0">
                    <flux:badge color="{{ $sub->expires_at->isPast() ? 'red' : ($sub->expires_at->diffInDays() < 3 ? 'yellow' : 'green') }}" size="sm">
                        {{ $sub->expires_at->diffForHumans() }}
                    </flux:badge>
                </div>
            </div>
            @empty
            <div class="flex flex-col items-center justify-center py-12 text-center px-5">
                <div class="inline-flex items-center justify-center size-12 rounded-xl bg-gray-100 dark:bg-neutral-700 mb-3">
                    <x-lucide name="credit-card" class="size-6 text-gray-400 dark:text-neutral-500"/>
                </div>
                <p class="text-sm font-medium text-gray-600 dark:text-neutral-400">{{ __('No active subscriptions') }}</p>
            </div>
            @endforelse
        </div>

    </div>

    {{-- ══ RECENT PAYMENTS — Preline table ══ --}}
    <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 mb-6">

        <div class="px-5 py-4 border-b border-gray-100 dark:border-neutral-700">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-neutral-200">
                <x-lucide name="bar-chart-3" class="size-4 text-teal-500"/>
                {{ __('Recent Payments') }}
            </h2>
        </div>

        @if($this->recentPayments->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                <thead class="bg-gray-50 dark:bg-neutral-700/30">
                    <tr>
                        <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Plan') }}</th>
                        <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Router') }}</th>
                        <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                        <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                    @foreach($this->recentPayments as $payment)
                    <tr class="hover:bg-gray-50 dark:hover:bg-neutral-700/20 transition-colors">
                        <td class="px-5 py-3 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-neutral-200">
                            {{ $payment->subscription->plan->name ?? '–' }}
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                            {{ $payment->subscription->router->name ?? '–' }}
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap text-sm font-semibold text-gray-800 dark:text-neutral-200">
                            TZS {{ number_format($payment->amount) }}
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            <flux:badge color="{{ $payment->status === 'success' ? 'green' : ($payment->status === 'pending' ? 'yellow' : 'red') }}" size="sm">
                                {{ $payment->status === 'success' ? 'Paid' : ucfirst($payment->status) }}
                            </flux:badge>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-400 dark:text-neutral-500">
                            {{ $payment->created_at->format('d M Y') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <div class="inline-flex items-center justify-center size-12 rounded-xl bg-gray-100 dark:bg-neutral-700 mb-3">
                <x-lucide name="bar-chart-3" class="size-6 text-gray-400 dark:text-neutral-500"/>
            </div>
            <p class="text-sm text-gray-500 dark:text-neutral-400">{{ __('No payments yet') }}</p>
        </div>
        @endif

    </div>

    {{-- ══ QUICK ACTIONS — Preline link cards ══ --}}
    <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-neutral-200 mb-4">{{ __('Quick Actions') }}</h2>
        <div class="grid grid-cols-3 gap-3 sm:grid-cols-3">

            <a href="{{ route('customer.routers.claim') }}" wire:navigate
               class="group flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 hover:bg-sky-50 hover:border-sky-200 dark:border-neutral-700 dark:bg-neutral-700/30 dark:hover:bg-sky-900/20 dark:hover:border-sky-800 transition-all">
                <div class="inline-flex items-center justify-center size-10 rounded-xl bg-sky-100 dark:bg-sky-800/30 group-hover:bg-sky-200 dark:group-hover:bg-sky-700/40 transition-colors">
                    <x-lucide name="plus-circle" class="size-5 text-sky-600 dark:text-sky-400"/>
                </div>
                <span class="text-xs font-medium text-gray-700 dark:text-neutral-300 text-center">{{ __('Add Router') }}</span>
            </a>

            <a href="{{ route('customer.routers') }}" wire:navigate
               class="group flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 hover:bg-emerald-50 hover:border-emerald-200 dark:border-neutral-700 dark:bg-neutral-700/30 dark:hover:bg-emerald-900/20 dark:hover:border-emerald-800 transition-all">
                <div class="inline-flex items-center justify-center size-10 rounded-xl bg-emerald-100 dark:bg-emerald-800/30 group-hover:bg-emerald-200 dark:group-hover:bg-emerald-700/40 transition-colors">
                    <x-lucide name="monitor" class="size-5 text-emerald-600 dark:text-emerald-400"/>
                </div>
                <span class="text-xs font-medium text-gray-700 dark:text-neutral-300 text-center">{{ __('Setup Scripts') }}</span>
            </a>

            <a href="{{ route('customer.subscriptions') }}" wire:navigate
               class="group flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 hover:bg-violet-50 hover:border-violet-200 dark:border-neutral-700 dark:bg-neutral-700/30 dark:hover:bg-violet-900/20 dark:hover:border-violet-800 transition-all">
                <div class="inline-flex items-center justify-center size-10 rounded-xl bg-violet-100 dark:bg-violet-800/30 group-hover:bg-violet-200 dark:group-hover:bg-violet-700/40 transition-colors">
                    <x-lucide name="credit-card" class="size-5 text-violet-600 dark:text-violet-400"/>
                </div>
                <span class="text-xs font-medium text-gray-700 dark:text-neutral-300 text-center">{{ __('Buy More Time') }}</span>
            </a>

        </div>
    </div>

</div>
