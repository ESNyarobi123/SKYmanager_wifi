@php
/**
 * Unified role-aware sidebar layout.
 * Theme colours, nav groups, dashboard route, and logout action are all
 * derived from the authenticated user's primary Spatie role.
 */
$user      = auth()->user();
$role      = $user?->roles->first()?->name ?? 'admin';
$isAdmin   = in_array($role, ['admin', 'super-admin']);
$isCustomer = $role === 'customer';
$isReseller = $role === 'reseller';

// ── Theme tokens ──────────────────────────────────────────────────────────
$theme = match ($role) {
    'super-admin' => [
        'bg'         => 'bg-[#3730a3]',
        'border'     => 'border-indigo-900/60',
        'ping'       => 'bg-indigo-300',
        'dot'        => 'bg-indigo-200',
        'label'      => 'text-indigo-200',
        'group_text' => 'text-indigo-300/70',
        'divider'    => 'bg-indigo-700/50',
        'inactive'   => 'text-indigo-200 hover:bg-white/10 hover:text-white',
        'icon_def'   => 'text-indigo-300 group-hover:text-white',
        'toggle_txt' => 'text-indigo-300',
        'footer_bdr' => 'border-indigo-800/60',
        'footer_txt' => 'text-indigo-100',
        'foot_sub'   => 'text-indigo-300',
        'breadcrumb' => 'text-indigo-600 dark:text-indigo-400',
        'role_label' => 'Super Admin',
    ],
    'reseller' => [
        'bg'         => 'bg-[#065f46]',
        'border'     => 'border-emerald-900/60',
        'ping'       => 'bg-emerald-300',
        'dot'        => 'bg-emerald-200',
        'label'      => 'text-emerald-200',
        'group_text' => 'text-emerald-300/70',
        'divider'    => 'bg-emerald-700/50',
        'inactive'   => 'text-emerald-200 hover:bg-white/10 hover:text-white',
        'icon_def'   => 'text-emerald-300 group-hover:text-white',
        'toggle_txt' => 'text-emerald-300',
        'footer_bdr' => 'border-emerald-800/60',
        'footer_txt' => 'text-emerald-100',
        'foot_sub'   => 'text-emerald-300',
        'breadcrumb' => 'text-emerald-600 dark:text-emerald-400',
        'role_label' => 'Reseller',
    ],
    'customer' => [
        'bg'         => 'bg-[#0369a1]',
        'border'     => 'border-sky-800/60',
        'ping'       => 'bg-sky-200',
        'dot'        => 'bg-sky-100',
        'label'      => 'text-sky-200',
        'group_text' => 'text-sky-200/70',
        'divider'    => 'bg-sky-700/50',
        'inactive'   => 'text-sky-100 hover:bg-white/10 hover:text-white',
        'icon_def'   => 'text-sky-300 group-hover:text-white',
        'toggle_txt' => 'text-sky-300',
        'footer_bdr' => 'border-sky-700/60',
        'footer_txt' => 'text-sky-100',
        'foot_sub'   => 'text-sky-200',
        'breadcrumb' => 'text-sky-600 dark:text-sky-400',
        'role_label' => 'Customer Portal',
    ],
    default => [ // admin
        'bg'         => 'bg-[#4b0082]',
        'border'     => 'border-purple-900/60',
        'ping'       => 'bg-purple-300',
        'dot'        => 'bg-purple-200',
        'label'      => 'text-purple-300',
        'group_text' => 'text-purple-300/70',
        'divider'    => 'bg-purple-700/50',
        'inactive'   => 'text-purple-200 hover:bg-white/10 hover:text-white',
        'icon_def'   => 'text-purple-300 group-hover:text-white',
        'toggle_txt' => 'text-purple-300',
        'footer_bdr' => 'border-purple-800/60',
        'footer_txt' => 'text-purple-100',
        'foot_sub'   => 'text-purple-300',
        'breadcrumb' => 'text-purple-600 dark:text-purple-400',
        'role_label' => 'Admin Panel',
    ],
};

// ── Dashboard & logout routes ──────────────────────────────────────────────
$homeRoute   = $isCustomer ? 'customer.dashboard' : 'dashboard';
$logoutRoute = $isCustomer ? 'customer.logout'    : 'logout';
$logoutMethod = 'POST';

// ── User subtitle (email for admins, phone for customers) ──────────────────
$userSubtitle = $isCustomer ? ($user?->phone ?? '') : ($user?->email ?? '');

// ── Nav groups ────────────────────────────────────────────────────────────
$navItem = fn(string $routeName, string $icon, string $label) => [
    'route'  => $routeName,
    'icon'   => $icon,
    'label'  => $label,
    'active' => request()->routeIs($routeName),
];

$adminBillingNav = [
    $navItem('admin.plans', 'credit-card', 'Billing Plans'),
    $navItem('admin.vouchers', 'ticket', 'Vouchers'),
    $navItem('admin.analytics', 'bar-chart-3', 'Analytics'),
];
if ($user?->can('reports.view')) {
    $adminBillingNav[] = $navItem('admin.reports', 'file-bar-chart', 'Reports');
}
if ($user?->can('reports.export')) {
    $adminBillingNav[] = $navItem('admin.support-exports', 'arrow-down-tray', 'Export center');
}

$resellerOpsNav = [
    $navItem('admin.router-operations', 'router', 'Router operations'),
    $navItem('admin.hotspot-payment-support', 'credit-card', 'Pay authorizations'),
];
if ($user?->can('reports.view')) {
    $resellerOpsNav[] = $navItem('admin.reports', 'file-bar-chart', 'Reports');
}
if ($user?->can('reports.export')) {
    $resellerOpsNav[] = $navItem('admin.support-exports', 'arrow-down-tray', 'Export center');
}

$groups = match (true) {
    $isAdmin || $role === 'super-admin' => [
        'Overview'  => [$navItem('dashboard', 'layout-dashboard', 'Dashboard')],
        'Network'   => [
            $navItem('admin.routers',    'server',        'Routers'),
            $navItem('admin.router-operations', 'router',   'Router operations'),
            $navItem('admin.hotspot-payment-support', 'credit-card', 'Pay authorizations'),
            $navItem('admin.monitoring', 'activity',      'Monitoring'),
            $navItem('admin.sessions',   'signal',        'Active Sessions'),
            $navItem('admin.hotspot',    'wifi',          'Hotspot System'),
        ],
        'Customers' => [
            $navItem('admin.customers',        'users',          'Hotspot Users'),
            $navItem('admin.portal-customers', 'circle-user',    'Portal Accounts'),
            $navItem('admin.activity-log',     'clipboard-list', 'Activity Log'),
        ],
        'Billing'   => $adminBillingNav,
        'Tools'     => [
            $navItem('admin.tools',           'wrench',   'Network Tools'),
            $navItem('admin.radius',          'shield',   'RADIUS Tools'),
            $navItem('admin.system-settings', 'settings', 'System Settings'),
        ],
    ],
    $isCustomer => [
        'Overview'    => [$navItem('customer.dashboard', 'layout-dashboard', 'Dashboard')],
        'My Network'  => [
            $navItem('customer.routers',       'server',      'My Routers'),
            $navItem('customer.client-sessions', 'users',     'Client sessions'),
            $navItem('customer.routers.claim', 'plus-circle', 'Claim a Router'),
        ],
        'Billing'     => [
            $navItem('customer.plans',         'layout-grid', 'My Plans'),
            $navItem('customer.subscriptions', 'credit-card', 'Subscriptions'),
            $navItem('customer.invoices',      'file-text',   'Invoices'),
        ],
        'Account'     => [
            $navItem('customer.referral',         'gift',     'Referral Program'),
            $navItem('customer.payment-settings', 'settings', 'Payment Settings'),
        ],
    ],
    default => [  // reseller
        'Overview' => [$navItem('dashboard', 'layout-dashboard', 'Dashboard')],
        'Operations' => $resellerOpsNav,
    ],
};

// ── Notification badge (customer only) ────────────────────────────────────
$unread = $isCustomer ? ($user?->unreadNotifications()->count() ?? 0) : 0;

$storageKey = $isCustomer ? 'customerSidebar' : 'adminSidebar';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
    <style>[x-cloak]{display:none!important}</style>
</head>
<body
    x-data="{
        open: JSON.parse(localStorage.getItem('{{ $storageKey }}') ?? 'true'),
        toggle() { this.open = !this.open; localStorage.setItem('{{ $storageKey }}', JSON.stringify(this.open)); },
        search: '',
        showSearch: false,
    }"
    class="bg-gray-50 dark:bg-neutral-900 min-h-screen font-sans antialiased">

{{-- ══ SIDEBAR ══ --}}
<div id="hs-application-sidebar"
     x-bind:class="open ? 'lg:w-[260px]' : 'lg:w-[68px]'"
     class="hs-overlay [--auto-close:lg] hs-overlay-open:translate-x-0 -translate-x-full transition-all duration-300 transform w-[260px] fixed inset-y-0 start-0 z-[60] flex flex-col overflow-hidden {{ $theme['bg'] }} border-e {{ $theme['border'] }}
            lg:translate-x-0 lg:end-auto lg:bottom-0"
     role="dialog" aria-label="{{ $theme['role_label'] }} Sidebar">

    {{-- ── Logo ── --}}
    <div class="flex-shrink-0 border-b {{ $theme['border'] }} transition-all duration-300"
         x-bind:class="open ? 'px-5 py-5' : 'px-2 py-4'">
        <a href="{{ route($homeRoute) }}" wire:navigate
           x-bind:class="open ? 'gap-x-3' : 'justify-center'"
           class="flex items-center group transition-all duration-300">
            <div class="relative flex-shrink-0 flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 border border-white/20 shadow-inner transition group-hover:bg-white/20">
                <x-lucide name="wifi" class="size-4 text-white"/>
                <span class="absolute -top-0.5 -right-0.5 flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full {{ $theme['ping'] }} opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full {{ $theme['dot'] }}"></span>
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
                <span class="block text-[10px] font-medium leading-none {{ $theme['label'] }} tracking-widest uppercase mt-0.5 whitespace-nowrap">{{ $theme['role_label'] }}</span>
            </div>
        </a>
    </div>

    {{-- ── Nav ── --}}
    <nav class="sidebar-scroll flex-1 min-h-0 overscroll-y-contain py-4 overflow-y-auto overflow-x-hidden transition-all duration-300 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
         x-bind:class="open ? 'px-3' : 'px-2'">

        @foreach($groups as $groupName => $items)
        <div class="{{ !$loop->first ? 'mt-4' : '' }}">
            <p x-show="open" x-cloak
               class="mb-1.5 px-2 text-[10px] font-semibold uppercase tracking-widest {{ $theme['group_text'] }} whitespace-nowrap">
                {{ $groupName }}
            </p>
            @if(!$loop->first)
            <div x-show="!open" x-cloak class="mb-2 mx-auto w-7 h-px {{ $theme['divider'] }}"></div>
            @endif

            @foreach($items as $item)
            <a href="{{ route($item['route']) }}" wire:navigate
               title="{{ $item['label'] }}"
               x-bind:class="open ? 'gap-x-3 px-2.5 justify-start' : 'justify-center px-0'"
               class="group flex items-center py-2 rounded-lg text-sm font-medium transition-all duration-150
                      {{ $item['active'] ? 'bg-white/20 text-white shadow-sm' : $theme['inactive'] }}">
                <x-lucide name="{{ $item['icon'] }}"
                          class="size-4 flex-shrink-0 transition-transform duration-150 group-hover:scale-110
                                 {{ $item['active'] ? 'text-white' : $theme['icon_def'] }}"/>
                <span x-show="open" x-cloak class="truncate whitespace-nowrap">{{ $item['label'] }}</span>
                @if($item['active'])
                <span x-show="open" x-cloak class="ms-auto h-1.5 w-1.5 rounded-full bg-white/80 flex-shrink-0"></span>
                @endif
            </a>
            @endforeach
        </div>
        @endforeach

        {{-- ── Notifications (customer only) ── --}}
        @if($isCustomer)
        <div class="mt-4">
            <div x-show="!open" x-cloak class="mb-2 mx-auto w-7 h-px {{ $theme['divider'] }}"></div>
            <p x-show="open" x-cloak
               class="mb-1.5 px-2 text-[10px] font-semibold uppercase tracking-widest {{ $theme['group_text'] }} whitespace-nowrap">
                Notifications
            </p>
            <a href="{{ route('customer.notifications') }}" wire:navigate
               title="Notifications"
               x-bind:class="open ? 'gap-x-3 px-2.5 justify-start' : 'justify-center px-0'"
               class="group relative flex items-center py-2 rounded-lg text-sm font-medium transition-all duration-150
                      {{ request()->routeIs('customer.notifications') ? 'bg-white/20 text-white shadow-sm' : $theme['inactive'] }}">
                <span class="relative flex-shrink-0">
                    <x-lucide name="bell"
                              class="size-4 transition-transform duration-150 group-hover:scale-110
                                     {{ request()->routeIs('customer.notifications') ? 'text-white' : $theme['icon_def'] }}"/>
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
        @endif

        {{-- ── Collapse / Expand toggle ── --}}
        <div class="mt-5 pt-4 border-t {{ $theme['divider'] }}">
            <button @click="toggle()"
                    x-bind:title="open ? 'Minimize sidebar' : 'Expand sidebar'"
                    x-bind:class="open ? 'gap-x-3 px-2.5 justify-start' : 'justify-center px-0'"
                    class="w-full group flex items-center py-2 rounded-lg text-sm font-medium {{ $theme['toggle_txt'] }} hover:bg-white/10 hover:text-white transition-all duration-150">
                <x-lucide name="chevrons-left"
                          class="size-4 flex-shrink-0 {{ $theme['toggle_txt'] }} group-hover:text-white transition-transform duration-300"
                          x-bind:class="open ? '' : 'rotate-180'"/>
                <span x-show="open" x-cloak class="whitespace-nowrap">Minimize</span>
            </button>
        </div>

    </nav>

    {{-- ── User footer ── --}}
    <div class="flex-shrink-0 border-t {{ $theme['footer_bdr'] }} px-3 py-3">

        {{-- Expanded --}}
        <div x-show="open" x-cloak>
            <flux:dropdown position="top" align="start" class="w-full">
                <flux:button variant="ghost"
                             class="w-full justify-start gap-3 px-2 py-2.5 {{ $theme['footer_txt'] }} hover:bg-white/10 hover:text-white rounded-lg">
                    <flux:avatar :name="$user->name" :initials="$user->initials()"
                                 size="sm" class="bg-white/20 text-white text-xs shrink-0"/>
                    <div class="flex-1 text-start text-sm leading-tight min-w-0">
                        <span class="block truncate font-semibold text-white">{{ $user->name }}</span>
                        <span class="block truncate text-xs {{ $theme['foot_sub'] }}">{{ $userSubtitle }}</span>
                    </div>
                    <x-lucide name="chevron-up" class="size-3.5 {{ $theme['foot_sub'] }} shrink-0"/>
                </flux:button>
                <flux:menu>
                    @if(!$isCustomer)
                    <flux:menu.item :href="route('profile.edit')" wire:navigate icon="cog">Settings</flux:menu.item>
                    <flux:menu.separator/>
                    @endif
                    <form method="{{ $logoutMethod }}" action="{{ route($logoutRoute) }}" class="w-full">
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
                    <flux:avatar :initials="$user->initials()" size="sm" class="bg-white/20 text-white text-xs"/>
                </button>
                <flux:menu>
                    <div class="px-3 py-2">
                        <p class="text-sm font-semibold dark:text-white">{{ $user->name }}</p>
                        <p class="text-xs text-gray-500">{{ $userSubtitle }}</p>
                    </div>
                    <flux:menu.separator/>
                    @if(!$isCustomer)
                    <flux:menu.item :href="route('profile.edit')" wire:navigate icon="cog">Settings</flux:menu.item>
                    <flux:menu.separator/>
                    @endif
                    <form method="{{ $logoutMethod }}" action="{{ route($logoutRoute) }}" class="w-full">
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

            {{-- Left: mobile toggle + desktop toggle + breadcrumb ── --}}
            <div class="flex items-center gap-x-3">
                <button type="button"
                        class="size-8 inline-flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 lg:hidden"
                        data-hs-overlay="#hs-application-sidebar"
                        aria-controls="hs-application-sidebar"
                        aria-label="Toggle navigation">
                    <x-lucide name="menu" class="size-4"/>
                </button>
                <button type="button" @click="toggle()"
                        class="hidden lg:inline-flex size-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 transition-colors"
                        x-bind:title="open ? 'Minimize sidebar' : 'Expand sidebar'">
                    <x-lucide name="menu" class="size-4"/>
                </button>
                <div class="hidden sm:block">
                    <nav class="flex items-center gap-x-1 text-sm text-gray-500 dark:text-neutral-400">
                        <span class="font-medium {{ $theme['breadcrumb'] }}">{{ $theme['role_label'] }}</span>
                        <x-lucide name="chevron-right" class="size-3"/>
                        <span>{{ $title ?? 'Dashboard' }}</span>
                    </nav>
                </div>
            </div>

            {{-- Right: global search + notifications (customer) + mobile user ── --}}
            <div class="flex items-center gap-x-2">

                {{-- Global search bar ──────────────────────────────────── --}}
                <div class="relative hidden sm:block">
                    <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                        <x-lucide name="search" class="size-3.5 text-gray-400 dark:text-neutral-500"/>
                    </div>
                    <input type="search"
                           x-model="search"
                           @keydown.escape="search = ''"
                           placeholder="{{ __('Search…') }}"
                           class="block w-40 lg:w-56 rounded-lg border border-gray-200 bg-white py-1.5 ps-9 pe-3 text-sm text-gray-700 placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:placeholder-neutral-500 dark:focus:border-blue-500 transition-all"/>
                </div>

                {{-- Notifications bell (customer: badge; admin: plain) ── --}}
                @if($isCustomer)
                <a href="{{ route('customer.notifications') }}" wire:navigate
                   class="relative size-9 inline-flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 transition-colors">
                    <x-lucide name="bell" class="size-4"/>
                    @if($unread > 0)
                    <span class="absolute top-1 right-1 flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                    </span>
                    @endif
                </a>
                @else
                <a href="{{ route('dashboard') }}" wire:navigate
                   class="size-9 inline-flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 transition-colors">
                    <x-lucide name="bell" class="size-4"/>
                </a>
                @endif

                {{-- Mobile user menu ─────────────────────────────────── --}}
                <div class="lg:hidden">
                    <flux:dropdown position="bottom" align="end">
                        <flux:profile :initials="$user->initials()" icon-trailing="chevron-down"/>
                        <flux:menu>
                            <div class="px-3 py-2">
                                <p class="text-sm font-semibold dark:text-white">{{ $user->name }}</p>
                                <p class="text-xs text-gray-500">{{ $userSubtitle }}</p>
                            </div>
                            <flux:menu.separator/>
                            @if(!$isCustomer)
                            <flux:menu.item :href="route('profile.edit')" wire:navigate icon="cog">Settings</flux:menu.item>
                            <flux:menu.separator/>
                            @endif
                            <form method="{{ $logoutMethod }}" action="{{ route($logoutRoute) }}" class="w-full">
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
