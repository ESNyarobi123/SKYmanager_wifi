<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-purple-900 bg-[#4b0082] text-white">
            <flux:sidebar.header class="border-b border-purple-800 pb-4">
                <div class="flex items-center gap-2 px-1">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10">
                        <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                    </div>
                    <span class="text-lg font-bold text-white">SKYmanager</span>
                </div>
                <flux:sidebar.collapse class="lg:hidden text-white" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="mt-2">
                <flux:sidebar.group :heading="__('Overview')" class="grid [&_.flux-sidebar-group-heading]:text-purple-300 [&_.flux-sidebar-group-heading]:text-xs [&_.flux-sidebar-group-heading]:uppercase [&_.flux-sidebar-group-heading]:tracking-wider">
                    <flux:sidebar.item
                        icon="home"
                        :href="route('dashboard')"
                        :current="request()->routeIs('dashboard')"
                        wire:navigate
                        class="text-purple-100 hover:bg-white/10 hover:text-white [&[aria-current=page]]:bg-white/20 [&[aria-current=page]]:text-white"
                    >
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Network')" class="grid mt-2 [&_.flux-sidebar-group-heading]:text-purple-300 [&_.flux-sidebar-group-heading]:text-xs [&_.flux-sidebar-group-heading]:uppercase [&_.flux-sidebar-group-heading]:tracking-wider">
                    <flux:sidebar.item
                        icon="server"
                        :href="route('admin.routers')"
                        :current="request()->routeIs('admin.routers')"
                        wire:navigate
                        class="text-purple-100 hover:bg-white/10 hover:text-white [&[aria-current=page]]:bg-white/20 [&[aria-current=page]]:text-white"
                    >
                        {{ __('Routers') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        icon="signal"
                        :href="route('admin.sessions')"
                        :current="request()->routeIs('admin.sessions')"
                        wire:navigate
                        class="text-purple-100 hover:bg-white/10 hover:text-white [&[aria-current=page]]:bg-white/20 [&[aria-current=page]]:text-white"
                    >
                        {{ __('Active Sessions') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Billing')" class="grid mt-2 [&_.flux-sidebar-group-heading]:text-purple-300 [&_.flux-sidebar-group-heading]:text-xs [&_.flux-sidebar-group-heading]:uppercase [&_.flux-sidebar-group-heading]:tracking-wider">
                    <flux:sidebar.item
                        icon="credit-card"
                        :href="route('admin.plans')"
                        :current="request()->routeIs('admin.plans')"
                        wire:navigate
                        class="text-purple-100 hover:bg-white/10 hover:text-white [&[aria-current=page]]:bg-white/20 [&[aria-current=page]]:text-white"
                    >
                        {{ __('Billing Plans') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        icon="chart-bar"
                        :href="route('admin.analytics')"
                        :current="request()->routeIs('admin.analytics')"
                        wire:navigate
                        class="text-purple-100 hover:bg-white/10 hover:text-white [&[aria-current=page]]:bg-white/20 [&[aria-current=page]]:text-white"
                    >
                        {{ __('Analytics') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block border-t border-purple-800 pt-3 [&]:text-purple-100" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
