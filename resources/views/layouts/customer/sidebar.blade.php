<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
    <style>[x-cloak]{display:none!important}</style>
</head>
<body
    x-data="{
        open: JSON.parse(localStorage.getItem('customerSidebar') ?? 'true'),
        toggle() { this.open = !this.open; localStorage.setItem('customerSidebar', JSON.stringify(this.open)); }
    }"
    class="bg-gray-50 dark:bg-neutral-900 min-h-screen font-sans antialiased">

{{-- ══ SIDEBAR ══ --}}
<div id="hs-customer-sidebar"
     x-bind:class="open ? 'lg:w-[260px]' : 'lg:w-[68px]'"
     class="hs-overlay [--auto-close:lg] hs-overlay-open:translate-x-0 -translate-x-full transition-all duration-300 transform w-[260px] fixed inset-y-0 start-0 z-[60] flex flex-col overflow-hidden bg-[#0369a1] border-e border-sky-800/60
            lg:translate-x-0 lg:end-auto lg:bottom-0
            [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-track]:bg-sky-800/30 [&::-webkit-scrollbar-thumb]:bg-sky-600/60"
     role="dialog" aria-label="Customer Sidebar">

    {{-- ── Logo ── --}}
    <div class="flex-shrink-0 border-b border-sky-700/60 transition-all duration-300"
         x-bind:class="open ? 'px-5 py-5' : 'px-2 py-4'">
        <a href="{{ route('customer.dashboard') }}" wire:navigate
           x-bind:class="open ? 'gap-x-3' : 'justify-center'"
           class="flex items-center group transition-all duration-300">
            <div class="relative flex-shrink-0 flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 border border-white/20 shadow-inner transition group-hover:bg-white/20">
                <x-lucide name="wifi" class="size-4 text-white"/>
                <span class="absolute -top-0.5 -right-0.5 flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-sky-200 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-sky-100"></span>
                </span>
            </div>
            <div x-show="open" x-cloak
                 x-transition:enter="transition-opacity duration-200 delay-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                <span class="block text-[15px] font-bold leading-none text-white tracking-tight whitespace-nowrap">SKYmanager</span>
                <span class="block text-[10px] font-medium leading-none text-sky-200 tracking-widest uppercase mt-0.5 whitespace-nowrap">Customer Portal</span>
            </div>
        </a>
    </div>

    {{-- ── Nav ── --}}
    <nav class="flex-1 py-4 overflow-y-auto overflow-x-hidden transition-all duration-300"
         x-bind:class="open ? 'px-3' : 'px-2'">

        @php
        $navItem = fn(string $route, string $icon, string $label) => [
            'route' => $route, 'icon' => $icon, 'label' => $label,
            'active' => request()->routeIs($route),
        ];
        $groups = [
            'Overview' => [
                $navItem('customer.dashboard', 'layout-dashboard', 'Dashboard'),
            ],
            'My Network' => [
                $navItem('customer.routers', 'server', 'My Routers'),
                $navItem('customer.routers.claim', 'plus-circle', 'Claim a Router'),
            ],
            'Billing' => [
                $navItem('customer.subscriptions', 'credit-card', 'Subscriptions'),
                $navItem('customer.invoices', 'file-text', 'Invoices'),
            ],
            'Account' => [
                $navItem('customer.referral', 'gift', 'Referral Program'),
                $navItem('customer.payment-settings', 'settings', 'Payment Settings'),
            ],
        ];
        @endphp

        @foreach($groups as $groupName => $items)
        <div class="{{ !$loop->first ? 'mt-4' : '' }}">
            <p x-show="open" x-cloak
               class="mb-1.5 px-2 text-[10px] font-semibold uppercase tracking-widest text-sky-200/70 whitespace-nowrap">
                {{ $groupName }}
            </p>
            @if(!$loop->first)
            <div x-show="!open" x-cloak class="mb-2 mx-auto w-7 h-px bg-sky-700/50"></div>
            @endif

            @foreach($items as $item)
            <a href="{{ route($item['route']) }}" wire:navigate
               title="{{ $item['label'] }}"
               x-bind:class="open ? 'gap-x-3 px-2.5 justify-start' : 'justify-center px-0'"
               class="group flex items-center py-2 rounded-lg text-sm font-medium transition-all duration-150
                      {{ $item['active']
                          ? 'bg-white/20 text-white shadow-sm'
                          : 'text-sky-100 hover:bg-white/10 hover:text-white' }}">
                <x-lucide name="{{ $item['icon'] }}"
                          class="size-4 flex-shrink-0 transition-transform duration-150 group-hover:scale-110
                                 {{ $item['active'] ? 'text-white' : 'text-sky-300 group-hover:text-white' }}"/>
                <span x-show="open" x-cloak class="truncate whitespace-nowrap">{{ $item['label'] }}</span>
                @if($item['active'])
                <span x-show="open" x-cloak class="ms-auto h-1.5 w-1.5 rounded-full bg-white/80 flex-shrink-0"></span>
                @endif
            </a>
            @endforeach
        </div>
        @endforeach

        {{-- ── Notifications ── --}}
        @php $unread = auth()->user()?->unreadNotifications()->count() ?? 0; @endphp
        <div class="mt-4">
            <div x-show="!open" x-cloak class="mb-2 mx-auto w-7 h-px bg-sky-700/50"></div>
            <p x-show="open" x-cloak
               class="mb-1.5 px-2 text-[10px] font-semibold uppercase tracking-widest text-sky-200/70 whitespace-nowrap">
                Notifications
            </p>
            <a href="{{ route('customer.notifications') }}" wire:navigate
               title="Notifications"
               x-bind:class="open ? 'gap-x-3 px-2.5 justify-start' : 'justify-center px-0'"
               class="group relative flex items-center py-2 rounded-lg text-sm font-medium transition-all duration-150
                      {{ request()->routeIs('customer.notifications')
                          ? 'bg-white/20 text-white shadow-sm'
                          : 'text-sky-100 hover:bg-white/10 hover:text-white' }}">
                <span class="relative flex-shrink-0">
                    <x-lucide name="bell"
                              class="size-4 transition-transform duration-150 group-hover:scale-110
                                     {{ request()->routeIs('customer.notifications') ? 'text-white' : 'text-sky-300 group-hover:text-white' }}"/>
                    @if($unread > 0)
                    <span class="absolute -top-1 -right-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-red-500 text-[8px] font-bold text-white leading-none">
                        {{ $unread > 9 ? '9+' : $unread }}
                    </span>
                    @endif
                </span>
                <span x-show="open" x-cloak class="truncate whitespace-nowrap">Notifications</span>
                @if($unread > 0)
                <span x-show="open" x-cloak
                      class="ms-auto flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                    {{ $unread > 99 ? '99+' : $unread }}
                </span>
                @elseif(request()->routeIs('customer.notifications'))
                <span x-show="open" x-cloak class="ms-auto h-1.5 w-1.5 rounded-full bg-white/80"></span>
                @endif
            </a>
        </div>

        {{-- ── Collapse / Expand toggle ── --}}
        <div class="mt-5 pt-4 border-t border-sky-700/40">
            <button @click="toggle()"
                    x-bind:title="open ? 'Minimize sidebar' : 'Expand sidebar'"
                    x-bind:class="open ? 'gap-x-3 px-2.5 justify-start' : 'justify-center px-0'"
                    class="w-full group flex items-center py-2 rounded-lg text-sm font-medium text-sky-300 hover:bg-white/10 hover:text-white transition-all duration-150">
                <x-lucide name="chevrons-left"
                          class="size-4 flex-shrink-0 text-sky-300 group-hover:text-white transition-transform duration-300"
                          x-bind:class="open ? '' : 'rotate-180'"/>
                <span x-show="open" x-cloak class="whitespace-nowrap">Minimize</span>
            </button>
        </div>

    </nav>

    {{-- ── User footer ── --}}
    <div class="flex-shrink-0 border-t border-sky-700/60 px-3 py-3">

        {{-- Expanded --}}
        <div x-show="open" x-cloak>
            <flux:dropdown position="top" align="start" class="w-full">
                <flux:button variant="ghost"
                             class="w-full justify-start gap-3 px-2 py-2.5 text-sky-100 hover:bg-white/10 hover:text-white rounded-lg">
                    <flux:avatar :name="auth()->user()->name"
                                 :initials="auth()->user()->initials()"
                                 size="sm" class="bg-white/20 text-white text-xs shrink-0"/>
                    <div class="flex-1 text-start text-sm leading-tight min-w-0">
                        <span class="block truncate font-semibold text-white">{{ auth()->user()->name }}</span>
                        <span class="block truncate text-xs text-sky-200">{{ auth()->user()->phone }}</span>
                    </div>
                    <x-lucide name="chevron-up" class="size-3.5 text-sky-300 shrink-0"/>
                </flux:button>
                <flux:menu>
                    <flux:menu.separator/>
                    <form method="POST" action="{{ route('customer.logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">Log out</flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </div>

        {{-- Collapsed: avatar only --}}
        <div x-show="!open" x-cloak class="flex justify-center">
            <flux:dropdown position="top" align="start">
                <button class="flex items-center justify-center size-9 rounded-lg hover:bg-white/10 transition">
                    <flux:avatar :initials="auth()->user()->initials()" size="sm" class="bg-white/20 text-white text-xs"/>
                </button>
                <flux:menu>
                    <div class="px-3 py-2">
                        <p class="text-sm font-semibold dark:text-white">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-gray-500">{{ auth()->user()->phone }}</p>
                    </div>
                    <flux:menu.separator/>
                    <form method="POST" action="{{ route('customer.logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">Log out</flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </div>

    </div>

</div>
{{-- ══ /SIDEBAR ══ --}}

{{-- ══ MAIN CONTENT AREA ══ --}}
<div class="transition-all duration-300"
     x-bind:class="open ? 'lg:ms-[260px]' : 'lg:ms-[68px]'">

    {{-- ── Sticky topbar ── --}}
    <header class="sticky top-0 z-[48] w-full bg-white/80 dark:bg-neutral-900/80 border-b border-gray-200 dark:border-neutral-700 backdrop-blur-xl">
        <nav class="flex items-center justify-between gap-x-3 px-4 sm:px-6 py-3">

            {{-- Left: mobile overlay toggle + desktop sidebar toggle + breadcrumb --}}
            <div class="flex items-center gap-x-3">
                {{-- Mobile toggle (Preline overlay) --}}
                <button type="button"
                        class="size-8 inline-flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 lg:hidden"
                        data-hs-overlay="#hs-customer-sidebar"
                        aria-controls="hs-customer-sidebar"
                        aria-label="Toggle navigation">
                    <x-lucide name="menu" class="size-4"/>
                </button>
                {{-- Desktop sidebar toggle --}}
                <button type="button" @click="toggle()"
                        class="hidden lg:inline-flex size-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 transition-colors"
                        x-bind:title="open ? 'Minimize sidebar' : 'Expand sidebar'">
                    <x-lucide name="menu" class="size-4"/>
                </button>
                <div class="hidden sm:block">
                    <nav class="flex items-center gap-x-1 text-sm text-gray-500 dark:text-neutral-400">
                        <span class="font-medium text-sky-600 dark:text-sky-400">Portal</span>
                        <x-lucide name="chevron-right" class="size-3"/>
                        <span>{{ $title ?? 'Dashboard' }}</span>
                    </nav>
                </div>
            </div>

            {{-- Right: notifications bell + mobile user menu --}}
            <div class="flex items-center gap-x-2">
                @php $unreadTop = auth()->user()?->unreadNotifications()->count() ?? 0; @endphp
                <a href="{{ route('customer.notifications') }}" wire:navigate
                   class="relative size-9 inline-flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 transition-colors">
                    <x-lucide name="bell" class="size-4"/>
                    @if($unreadTop > 0)
                    <span class="absolute top-1 right-1 flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                    </span>
                    @endif
                </a>
                <div class="lg:hidden">
                    <flux:dropdown position="bottom" align="end">
                        <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down"/>
                        <flux:menu>
                            <div class="px-3 py-2">
                                <p class="text-sm font-semibold dark:text-white">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-gray-500">{{ auth()->user()->phone }}</p>
                            </div>
                            <flux:menu.separator/>
                            <form method="POST" action="{{ route('customer.logout') }}" class="w-full">
                                @csrf
                                <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">Log out</flux:menu.item>
                            </form>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </div>

        </nav>
    </header>
    {{-- ── /Topbar ── --}}

    {{-- ── Page content ── --}}
    <main class="p-4 sm:p-6 lg:p-8">
        {{ $slot }}
    </main>

</div>
{{-- ══ /MAIN CONTENT AREA ══ --}}

@fluxScripts
</body>
</html>
