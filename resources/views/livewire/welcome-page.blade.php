<div>

{{-- ══════════════════════════════════════════════
     NAVBAR — Modern dark glassmorphism
══════════════════════════════════════════════ --}}
<header class="sticky top-0 inset-x-0 z-50 w-full border-b border-white/8 bg-slate-900/75 backdrop-blur-2xl">

    {{-- Top accent line --}}
    <div class="h-px w-full bg-gradient-to-r from-transparent via-sky-500/60 to-transparent"></div>

    <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8">

        {{-- ── Logo ── --}}
        <a href="{{ route('home') }}" class="group flex shrink-0 items-center gap-x-3">
            <div class="relative flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-sky-600 shadow-lg shadow-sky-500/40 transition-all duration-300 group-hover:shadow-sky-500/60 group-hover:scale-105">
                <svg class="h-4.5 w-4.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
                </svg>
                {{-- Glow dot --}}
                <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-sky-400 opacity-60"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-sky-500 ring-2 ring-slate-900"></span>
                </span>
            </div>
            <div class="flex flex-col">
                <span class="text-[15px] font-bold leading-none text-white tracking-tight">{{ $companyName }}</span>
                <span class="text-[10px] font-medium leading-none text-sky-400/80 tracking-widest uppercase mt-0.5">WiFi Manager</span>
            </div>
        </a>

        {{-- ── Desktop nav links ── --}}
        <div class="hidden md:flex items-center gap-x-1">
            <a href="#features"
               class="group relative px-3.5 py-2 text-sm font-medium text-slate-400 transition-colors duration-200 hover:text-white">
                Features
                <span class="absolute bottom-0 left-1/2 h-px w-0 -translate-x-1/2 bg-sky-400 transition-all duration-300 group-hover:w-4/5"></span>
            </a>
            <a href="#how-it-works"
               class="group relative px-3.5 py-2 text-sm font-medium text-slate-400 transition-colors duration-200 hover:text-white">
                How It Works
                <span class="absolute bottom-0 left-1/2 h-px w-0 -translate-x-1/2 bg-sky-400 transition-all duration-300 group-hover:w-4/5"></span>
            </a>
            <a href="#for-who"
               class="group relative px-3.5 py-2 text-sm font-medium text-slate-400 transition-colors duration-200 hover:text-white">
                For Who
                <span class="absolute bottom-0 left-1/2 h-px w-0 -translate-x-1/2 bg-sky-400 transition-all duration-300 group-hover:w-4/5"></span>
            </a>
        </div>

        {{-- ── Desktop CTA buttons ── --}}
        <div class="hidden md:flex items-center gap-x-2.5">
            {{-- Divider --}}
            <div class="h-5 w-px bg-white/10"></div>

            <a href="{{ route('customer.login') }}"
               class="inline-flex items-center gap-x-1.5 rounded-lg px-3.5 py-2 text-sm font-medium text-slate-300 ring-1 ring-white/10 transition-all duration-200 hover:bg-white/8 hover:text-white hover:ring-white/20">
                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Customer Portal
            </a>

            <a href="{{ route('customer.register') }}"
               class="inline-flex items-center gap-x-1.5 rounded-lg bg-sky-500 px-3.5 py-2 text-sm font-semibold text-white shadow-md shadow-sky-500/30 transition-all duration-200 hover:bg-sky-400 hover:shadow-sky-400/40 hover:scale-105">
                Get Started
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>

            <a href="{{ route('login') }}"
               class="inline-flex items-center gap-x-1.5 rounded-lg bg-white/5 px-3.5 py-2 text-sm font-medium text-slate-400 ring-1 ring-white/10 transition-all duration-200 hover:bg-purple-600/20 hover:text-purple-300 hover:ring-purple-500/40">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Admin
            </a>
        </div>

        {{-- ── Mobile burger ── --}}
        <button type="button"
            class="hs-collapse-toggle md:hidden inline-flex items-center justify-center size-9 rounded-lg border border-white/10 bg-white/5 text-slate-300 transition-all hover:bg-white/10 hover:text-white"
            id="hs-navbar-toggle" aria-expanded="false" aria-controls="hs-navbar-collapse"
            data-hs-collapse="#hs-navbar-collapse">
            <svg class="hs-collapse-open:hidden size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <line x1="3" x2="21" y1="6" y2="6"/><line x1="3" x2="21" y1="12" y2="12"/><line x1="3" x2="21" y1="18" y2="18"/>
            </svg>
            <svg class="hs-collapse-open:block hidden size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
            </svg>
        </button>

    </nav>

    {{-- ── Mobile menu ── --}}
    <div id="hs-navbar-collapse"
         class="hs-collapse hidden overflow-hidden transition-all duration-300 md:hidden border-t border-white/8 bg-slate-900/95 backdrop-blur-xl">
        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6">
            {{-- Mobile nav links --}}
            <div class="flex flex-col gap-y-1 mb-4">
                <a href="#features" class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/6 hover:text-white transition-all">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-sky-500/10 text-sky-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    </span>
                    Features
                </a>
                <a href="#how-it-works" class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/6 hover:text-white transition-all">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-purple-500/10 text-purple-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </span>
                    How It Works
                </a>
                <a href="#for-who" class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/6 hover:text-white transition-all">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                    </span>
                    For Who
                </a>
            </div>

            {{-- Mobile CTA --}}
            <div class="grid grid-cols-2 gap-2 pt-4 border-t border-white/8">
                <a href="{{ route('customer.login') }}"
                   class="flex items-center justify-center gap-x-1.5 rounded-xl border border-white/10 bg-white/5 py-3 text-sm font-medium text-slate-300 hover:bg-white/10 hover:text-white transition-all">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    Customer Login
                </a>
                <a href="{{ route('customer.register') }}"
                   class="flex items-center justify-center gap-x-1.5 rounded-xl bg-sky-500 py-3 text-sm font-semibold text-white shadow-lg shadow-sky-500/25 hover:bg-sky-400 transition-all">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    Get Started
                </a>
                <a href="{{ route('login') }}"
                   class="col-span-2 flex items-center justify-center gap-x-1.5 rounded-xl border border-purple-500/20 bg-purple-500/5 py-2.5 text-sm font-medium text-purple-300 hover:bg-purple-500/10 transition-all">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Admin Panel Login
                </a>
            </div>
        </div>
    </div>

</header>


{{-- ══════════════════════════════════════════════
     HERO — Preline dark gradient hero
══════════════════════════════════════════════ --}}
<div class="relative overflow-hidden bg-slate-900">

    {{-- Gradient orbs --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-48 -right-48 w-[600px] h-[600px] rounded-full bg-sky-600/20 blur-3xl"></div>
        <div class="absolute -bottom-48 -left-48 w-[500px] h-[500px] rounded-full bg-purple-600/15 blur-3xl"></div>
    </div>
    <div class="absolute inset-0 pointer-events-none" style="background-image:radial-gradient(circle,rgba(148,163,184,.05) 1px,transparent 1px);background-size:28px 28px"></div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 lg:py-32">

        {{-- Preline badge --}}
        <div class="flex justify-center mb-6">
            <span class="inline-flex items-center gap-x-2 py-1.5 px-3 rounded-full text-xs font-medium bg-sky-500/10 text-sky-400 border border-sky-500/20">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-sky-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-sky-500"></span>
                </span>
                Built for Tanzanian ISPs &amp; WiFi Entrepreneurs
            </span>
        </div>

        {{-- Headline --}}
        <div class="text-center max-w-4xl mx-auto">
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-white leading-tight tracking-tight">
                Run Your Hotspot Business
                <span class="block mt-1 bg-gradient-to-r from-sky-400 to-cyan-300 bg-clip-text text-transparent">on Autopilot</span>
            </h1>

            <p class="mt-6 text-lg text-slate-400 max-w-2xl mx-auto leading-relaxed">
                Complete MikroTik hotspot billing, per-customer ClickPesa integration, self-service customer portals,
                and real-time analytics — everything your WiFi business needs in one platform.
            </p>

            {{-- CTA --}}
            <div class="mt-10 flex flex-col sm:flex-row justify-center gap-4">
                <a href="{{ route('customer.register') }}"
                   class="py-3.5 px-6 inline-flex items-center justify-center gap-x-2 text-base font-semibold rounded-xl border border-transparent bg-sky-500 text-white hover:bg-sky-400 shadow-lg shadow-sky-500/30 transition-all hover:scale-105">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    Create Free Account
                </a>
                <a href="{{ route('customer.login') }}"
                   class="py-3.5 px-6 inline-flex items-center justify-center gap-x-2 text-base font-semibold rounded-xl border border-white/20 text-white hover:bg-white/10 backdrop-blur-sm transition-all">
                    Customer Login
                </a>
            </div>

            <p class="mt-4 text-sm text-slate-500">
                System administrator?
                <a href="{{ route('login') }}" class="text-slate-400 underline underline-offset-2 hover:text-white transition-colors">Access Admin Panel →</a>
            </p>
        </div>

        {{-- Dashboard mockup --}}
        <div class="mt-16 max-w-5xl mx-auto">
            <div class="rounded-2xl border border-white/10 overflow-hidden shadow-2xl shadow-black/50 ring-1 ring-white/5">
                {{-- Browser bar --}}
                <div class="bg-slate-800/80 backdrop-blur px-4 py-3 border-b border-white/10 flex items-center gap-2">
                    <div class="size-3 rounded-full bg-red-500/80"></div>
                    <div class="size-3 rounded-full bg-yellow-500/80"></div>
                    <div class="size-3 rounded-full bg-green-500/80"></div>
                    <div class="ml-3 flex-1 bg-slate-700/60 rounded-md px-3 py-1 text-xs font-mono text-slate-400">
                        app.skymanager.co.tz/customer/dashboard
                    </div>
                </div>
                {{-- Dashboard body --}}
                <div class="bg-slate-900/80 backdrop-blur p-4 sm:p-6">
                    {{-- Stats --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-slate-800/80 rounded-xl p-4 border border-white/5">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="size-7 flex items-center justify-center rounded-lg bg-sky-500/20">
                                    <svg class="size-3.5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                                </div>
                                <span class="text-xs text-slate-400">Active Routers</span>
                            </div>
                            <p class="text-lg font-bold text-white">12</p>
                        </div>
                        <div class="bg-slate-800/80 rounded-xl p-4 border border-white/5">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="size-7 flex items-center justify-center rounded-lg bg-emerald-500/20">
                                    <svg class="size-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <span class="text-xs text-slate-400">Revenue</span>
                            </div>
                            <p class="text-lg font-bold text-white">TZS 320K</p>
                        </div>
                        <div class="bg-slate-800/80 rounded-xl p-4 border border-white/5">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="size-7 flex items-center justify-center rounded-lg bg-violet-500/20">
                                    <svg class="size-3.5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                                </div>
                                <span class="text-xs text-slate-400">Online Users</span>
                            </div>
                            <p class="text-lg font-bold text-white">247</p>
                        </div>
                        <div class="bg-slate-800/80 rounded-xl p-4 border border-white/5">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="size-7 flex items-center justify-center rounded-lg bg-green-500/20">
                                    <svg class="size-3.5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                </div>
                                <span class="text-xs text-slate-400">ClickPesa</span>
                            </div>
                            <p class="text-lg font-bold text-white">Connected</p>
                        </div>
                    </div>
                    {{-- Bar chart --}}
                    <div class="mt-4 bg-slate-800/80 rounded-xl border border-white/5 p-4">
                        <div class="flex items-end gap-1 h-16">
                            <div class="flex-1 rounded-t-sm bg-sky-500/30" style="height:42%"></div>
                            <div class="flex-1 rounded-t-sm bg-sky-500/40" style="height:65%"></div>
                            <div class="flex-1 rounded-t-sm bg-sky-500/30" style="height:48%"></div>
                            <div class="flex-1 rounded-t-sm bg-sky-500/50" style="height:80%"></div>
                            <div class="flex-1 rounded-t-sm bg-sky-500/40" style="height:55%"></div>
                            <div class="flex-1 rounded-t-sm bg-sky-500/60" style="height:90%"></div>
                            <div class="flex-1 rounded-t-sm bg-sky-500/50" style="height:70%"></div>
                            <div class="flex-1 rounded-t-sm bg-sky-500/60" style="height:85%"></div>
                            <div class="flex-1 rounded-t-sm bg-sky-500/40" style="height:60%"></div>
                            <div class="flex-1 rounded-t-sm bg-sky-500/70" style="height:95%"></div>
                            <div class="flex-1 rounded-t-sm bg-sky-500/55" style="height:75%"></div>
                            <div class="flex-1 rounded-t-sm bg-sky-500/65" style="height:88%"></div>
                        </div>
                        <p class="mt-2 text-center text-xs text-slate-500">Revenue — Last 12 Months</p>
                    </div>
                </div>
            </div>
            <div class="mx-auto mt-3 h-8 w-3/4 rounded-full bg-sky-500/15 blur-2xl"></div>
        </div>

    </div>
</div>


{{-- ══════════════════════════════════════════════
     FEATURES — Preline card grid
══════════════════════════════════════════════ --}}
<div id="features" class="bg-white dark:bg-neutral-900 py-24 sm:py-32">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="max-w-2xl mx-auto text-center mb-12">
            <span class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-sky-100 text-sky-800 dark:bg-sky-500/10 dark:text-sky-400">
                Everything You Need
            </span>
            <h2 class="mt-4 text-3xl font-bold text-gray-800 dark:text-white sm:text-4xl lg:text-5xl">
                Built for serious WiFi operators
            </h2>
            <p class="mt-4 text-lg text-gray-600 dark:text-neutral-400">
                From a single router to a multi-site ISP — {{ $companyName }} scales with your business.
            </p>
        </div>

        {{-- Cards grid --}}
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">

            {{-- Card 1 --}}
            <div class="group flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl hover:shadow-md hover:-translate-y-0.5 transition-all dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70 p-6">
                <div class="mb-4 inline-flex items-center justify-center size-12 rounded-xl bg-sky-100 dark:bg-sky-800/30">
                    <svg class="size-6 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v10m0 0H5m4 0h10m0-10v10m0 0h4a2 2 0 002-2V5a2 2 0 00-2-2h-4m-6 14v4m6-4v4m-6 0h6"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">MikroTik Integration</h3>
                <p class="text-sm text-gray-600 dark:text-neutral-400 leading-relaxed">Zero-touch provisioning for hotspot users. Connect your MikroTik router and manage sessions, bandwidth profiles, and user access from one dashboard.</p>
            </div>

            {{-- Card 2 --}}
            <div class="group flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl hover:shadow-md hover:-translate-y-0.5 transition-all dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70 p-6">
                <div class="mb-4 inline-flex items-center justify-center size-12 rounded-xl bg-emerald-100 dark:bg-emerald-800/30">
                    <svg class="size-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">Per-Customer ClickPesa</h3>
                <p class="text-sm text-gray-600 dark:text-neutral-400 leading-relaxed">Each hotspot owner uses their own ClickPesa merchant account. Money flows directly to you — credentials stored with AES-256 encryption.</p>
            </div>

            {{-- Card 3 --}}
            <div class="group flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl hover:shadow-md hover:-translate-y-0.5 transition-all dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70 p-6">
                <div class="mb-4 inline-flex items-center justify-center size-12 rounded-xl bg-violet-100 dark:bg-violet-800/30">
                    <svg class="size-6 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">Self-Service Portal</h3>
                <p class="text-sm text-gray-600 dark:text-neutral-400 leading-relaxed">Customers manage their own routers, view subscriptions, download invoices, and track usage — without calling your support line.</p>
            </div>

            {{-- Card 4 --}}
            <div class="group flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl hover:shadow-md hover:-translate-y-0.5 transition-all dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70 p-6">
                <div class="mb-4 inline-flex items-center justify-center size-12 rounded-xl bg-amber-100 dark:bg-amber-800/30">
                    <svg class="size-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">Referral System</h3>
                <p class="text-sm text-gray-600 dark:text-neutral-400 leading-relaxed">Built-in referral engine rewards customers for bringing in new users. Automatic reward-day credits and full referral history tracking.</p>
            </div>

            {{-- Card 5 --}}
            <div class="group flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl hover:shadow-md hover:-translate-y-0.5 transition-all dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70 p-6">
                <div class="mb-4 inline-flex items-center justify-center size-12 rounded-xl bg-rose-100 dark:bg-rose-800/30">
                    <svg class="size-6 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">Invoices &amp; Billing</h3>
                <p class="text-sm text-gray-600 dark:text-neutral-400 leading-relaxed">Professional PDF invoices generated automatically on every payment. Full billing history with TZS currency support and easy download.</p>
            </div>

            {{-- Card 6 --}}
            <div class="group flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl hover:shadow-md hover:-translate-y-0.5 transition-all dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70 p-6">
                <div class="mb-4 inline-flex items-center justify-center size-12 rounded-xl bg-teal-100 dark:bg-teal-800/30">
                    <svg class="size-6 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">Security First</h3>
                <p class="text-sm text-gray-600 dark:text-neutral-400 leading-relaxed">AES-256 encrypted credentials, separate admin and customer guards, rate limiting, session management, and complete activity audit logs.</p>
            </div>

        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════
     HOW IT WORKS — Preline steps
══════════════════════════════════════════════ --}}
<div id="how-it-works" class="bg-gray-50 dark:bg-neutral-800 py-24 sm:py-32">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="max-w-2xl mx-auto text-center mb-12">
            <span class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-500/10 dark:text-purple-400">
                Simple Setup
            </span>
            <h2 class="mt-4 text-3xl font-bold text-gray-800 dark:text-white sm:text-4xl lg:text-5xl">
                Up and running in minutes
            </h2>
            <p class="mt-4 text-lg text-gray-600 dark:text-neutral-400">
                No technical expertise needed. Connect your router, set your plans, and start earning.
            </p>
        </div>

        {{-- Steps --}}
        <div class="grid sm:grid-cols-3 gap-8 lg:gap-12">

            <div class="text-center">
                <div class="relative mx-auto mb-6 inline-flex">
                    <div class="inline-flex items-center justify-center size-16 rounded-2xl bg-sky-500 shadow-xl shadow-sky-500/25">
                        <svg class="size-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                    </div>
                    <span class="absolute -top-2 -right-2 flex size-6 items-center justify-center rounded-full bg-white dark:bg-neutral-800 border-2 border-sky-200 dark:border-sky-700 text-xs font-bold text-sky-600 dark:text-sky-400">1</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Register &amp; Add Your Router</h3>
                <p class="text-sm text-gray-600 dark:text-neutral-400 leading-relaxed max-w-xs mx-auto">Create your customer account, claim your MikroTik router using its MAC address, and it appears in your dashboard within seconds.</p>
            </div>

            <div class="text-center">
                <div class="relative mx-auto mb-6 inline-flex">
                    <div class="inline-flex items-center justify-center size-16 rounded-2xl bg-purple-600 shadow-xl shadow-purple-600/25">
                        <svg class="size-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <span class="absolute -top-2 -right-2 flex size-6 items-center justify-center rounded-full bg-white dark:bg-neutral-800 border-2 border-purple-200 dark:border-purple-700 text-xs font-bold text-purple-600 dark:text-purple-400">2</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Connect Your ClickPesa</h3>
                <p class="text-sm text-gray-600 dark:text-neutral-400 leading-relaxed max-w-xs mx-auto">Enter your ClickPesa API credentials. Hit "Test Connection" to verify — every payment on your hotspot goes straight to your account.</p>
            </div>

            <div class="text-center">
                <div class="relative mx-auto mb-6 inline-flex">
                    <div class="inline-flex items-center justify-center size-16 rounded-2xl bg-emerald-500 shadow-xl shadow-emerald-500/25">
                        <svg class="size-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <span class="absolute -top-2 -right-2 flex size-6 items-center justify-center rounded-full bg-white dark:bg-neutral-800 border-2 border-emerald-200 dark:border-emerald-700 text-xs font-bold text-emerald-600 dark:text-emerald-400">3</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Customers Pay &amp; Connect</h3>
                <p class="text-sm text-gray-600 dark:text-neutral-400 leading-relaxed max-w-xs mx-auto">WiFi users see your hotspot portal, choose a plan, pay via USSD push, and automatically get internet access. Zero manual intervention.</p>
            </div>

        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════
     FOR WHO
══════════════════════════════════════════════ --}}
<div id="for-who" class="bg-white dark:bg-neutral-900 py-24 sm:py-32">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="max-w-2xl mx-auto text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-800 dark:text-white sm:text-4xl lg:text-5xl">
                Built for every scale
            </h2>
            <p class="mt-4 text-lg text-gray-600 dark:text-neutral-400">
                Whether you run one hotspot or fifty, {{ $companyName }} keeps things simple.
            </p>
        </div>

        <div class="grid sm:grid-cols-3 gap-6">

            <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl hover:shadow-md hover:-translate-y-1 transition-all dark:bg-neutral-800 dark:border-neutral-700 p-8">
                <div class="mb-4 text-4xl">☕</div>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-3">Café &amp; Restaurant Owners</h3>
                <p class="text-sm text-gray-600 dark:text-neutral-400 leading-relaxed mb-5">Offer paid WiFi to your customers. Set hourly or daily plans, accept mobile money payments, and monitor usage without touching the router.</p>
                <div class="mt-auto flex items-center gap-1.5 text-sm font-medium text-amber-600 dark:text-amber-400">
                    Get started
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </div>
            </div>

            <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl hover:shadow-md hover:-translate-y-1 transition-all dark:bg-neutral-800 dark:border-neutral-700 p-8">
                <div class="mb-4 text-4xl">🏘️</div>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-3">Community ISPs</h3>
                <p class="text-sm text-gray-600 dark:text-neutral-400 leading-relaxed mb-5">Serve a whole estate or apartment block. Manage dozens of routers, hundreds of users, and recurring subscriptions from one admin dashboard.</p>
                <div class="mt-auto flex items-center gap-1.5 text-sm font-medium text-sky-600 dark:text-sky-400">
                    Get started
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </div>
            </div>

            <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl hover:shadow-md hover:-translate-y-1 transition-all dark:bg-neutral-800 dark:border-neutral-700 p-8">
                <div class="mb-4 text-4xl">🏢</div>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-3">Corporate Campus</h3>
                <p class="text-sm text-gray-600 dark:text-neutral-400 leading-relaxed mb-5">Managed WiFi billing for offices, schools, and campuses. Time-based or data-capped plans with automatic expiry and invoice generation.</p>
                <div class="mt-auto flex items-center gap-1.5 text-sm font-medium text-purple-600 dark:text-purple-400">
                    Get started
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </div>
            </div>

        </div>

        {{-- Trust badges --}}
        <div class="mt-12 grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="flex items-center gap-3 py-3 px-4 rounded-xl border border-gray-200 bg-gray-50 dark:border-neutral-700 dark:bg-neutral-800">
                <svg class="size-5 text-sky-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span class="text-sm font-medium text-gray-700 dark:text-neutral-300">Secure &amp; Encrypted</span>
            </div>
            <div class="flex items-center gap-3 py-3 px-4 rounded-xl border border-gray-200 bg-gray-50 dark:border-neutral-700 dark:bg-neutral-800">
                <svg class="size-5 text-sky-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span class="text-sm font-medium text-gray-700 dark:text-neutral-300">Real-time Updates</span>
            </div>
            <div class="flex items-center gap-3 py-3 px-4 rounded-xl border border-gray-200 bg-gray-50 dark:border-neutral-700 dark:bg-neutral-800">
                <svg class="size-5 text-sky-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                <span class="text-sm font-medium text-gray-700 dark:text-neutral-300">SMS Notifications</span>
            </div>
            <div class="flex items-center gap-3 py-3 px-4 rounded-xl border border-gray-200 bg-gray-50 dark:border-neutral-700 dark:bg-neutral-800">
                <svg class="size-5 text-sky-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span class="text-sm font-medium text-gray-700 dark:text-neutral-300">Full Audit Logs</span>
            </div>
        </div>

    </div>
</div>


{{-- ══════════════════════════════════════════════
     DUAL CTA — Preline two-column CTA
══════════════════════════════════════════════ --}}
<div class="bg-slate-900 py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid sm:grid-cols-2 gap-6">

            {{-- Customer CTA --}}
            <div class="relative overflow-hidden rounded-2xl border border-sky-500/20 bg-gradient-to-br from-sky-500/10 to-slate-900 p-8 sm:p-10">
                <div class="absolute -top-16 -right-16 size-48 rounded-full bg-sky-500/15 blur-2xl pointer-events-none"></div>
                <div class="relative">
                    <span class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-sky-500/10 text-sky-400 border border-sky-500/20 mb-4">
                        For Hotspot Owners
                    </span>
                    <h3 class="text-2xl sm:text-3xl font-bold text-white mb-3">Start accepting payments today</h3>
                    <p class="text-slate-400 leading-relaxed mb-6">Register your account, connect your MikroTik router, link your ClickPesa merchant account, and go live. No contracts, no setup fees.</p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('customer.register') }}"
                           class="py-3 px-5 inline-flex items-center justify-center gap-x-2 text-sm font-semibold rounded-xl border border-transparent bg-sky-500 text-white hover:bg-sky-400 shadow-lg shadow-sky-500/20 transition-all hover:scale-105">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            Create Free Account
                        </a>
                        <a href="{{ route('customer.login') }}"
                           class="py-3 px-5 inline-flex items-center justify-center gap-x-2 text-sm font-medium rounded-xl border border-white/20 text-white hover:bg-white/10 transition-all">
                            Already have an account
                        </a>
                    </div>
                </div>
            </div>

            {{-- Admin CTA --}}
            <div class="relative overflow-hidden rounded-2xl border border-purple-500/20 bg-gradient-to-br from-purple-500/10 to-slate-900 p-8 sm:p-10">
                <div class="absolute -top-16 -right-16 size-48 rounded-full bg-purple-500/15 blur-2xl pointer-events-none"></div>
                <div class="relative">
                    <span class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-purple-500/10 text-purple-400 border border-purple-500/20 mb-4">
                        Platform Administrators
                    </span>
                    <h3 class="text-2xl sm:text-3xl font-bold text-white mb-3">Full platform control</h3>
                    <p class="text-slate-400 leading-relaxed mb-6">Manage all portal customers, configure billing plans, monitor hotspot activity, send notifications, and view the complete audit trail.</p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('login') }}"
                           class="py-3 px-5 inline-flex items-center justify-center gap-x-2 text-sm font-semibold rounded-xl border border-transparent bg-purple-600 text-white hover:bg-purple-500 shadow-lg shadow-purple-600/20 transition-all hover:scale-105">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Admin Panel Login
                        </a>
                    </div>
                    <p class="mt-4 text-xs text-slate-600">Separate authentication system · Role-based access</p>
                </div>
            </div>

        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════
     FOOTER — Preline footer
══════════════════════════════════════════════ --}}
<footer class="bg-gray-900 border-t border-white/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

        <div class="flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="flex flex-col items-center sm:items-start gap-2">
                <a href="{{ route('home') }}" class="flex items-center gap-x-2 text-lg font-bold text-white">
                    <div class="flex size-8 items-center justify-center rounded-lg bg-sky-500">
                        <svg class="size-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                    </div>
                    {{ $companyName }}
                </a>
                <p class="text-xs text-gray-500">Professional WiFi Hotspot Management</p>
            </div>

            <nav class="flex items-center gap-x-5 text-sm text-gray-500">
                <a href="{{ route('customer.login') }}" class="hover:text-white transition-colors">Customer Login</a>
                <a href="{{ route('customer.register') }}" class="hover:text-white transition-colors">Register</a>
                <a href="{{ route('login') }}" class="hover:text-white transition-colors">Admin</a>
            </nav>
        </div>

        <div class="mt-8 pt-6 border-t border-white/5 flex flex-col sm:flex-row items-center justify-between gap-3">
            <p class="text-xs text-gray-600">&copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.</p>
            <p class="text-xs text-gray-700">Powered by <span class="font-semibold text-gray-500">SKYmanager</span></p>
        </div>

    </div>
</footer>

</div>
