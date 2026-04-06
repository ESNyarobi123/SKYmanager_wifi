<div>

    {{-- ── Header (Flux + dark shell) ───────────────────────────────────── --}}
    <div class="dark sticky top-0 z-50 border-b border-white/10 bg-zinc-950/90 backdrop-blur-xl">
        <div class="h-px w-full bg-gradient-to-r from-transparent via-sky-500/50 to-transparent"></div>
        <header class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" wire:navigate class="group flex shrink-0 items-center gap-3">
                <div class="relative flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-sky-600 shadow-lg shadow-sky-500/35 ring-1 ring-white/10 transition group-hover:scale-[1.02]">
                    <flux:icon name="wifi" class="size-5 text-white" />
                    <span class="absolute -right-0.5 -top-0.5 flex size-2.5">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-sky-300 opacity-60"></span>
                        <span class="relative inline-flex size-2.5 rounded-full bg-emerald-400 ring-2 ring-zinc-950"></span>
                    </span>
                </div>
                <div class="flex flex-col">
                    <span class="text-[15px] font-semibold leading-none tracking-tight text-white">{{ $companyName }}</span>
                    <span class="mt-1 text-[10px] font-medium uppercase tracking-[0.2em] text-sky-400/90">{{ __('WiFi operations cloud') }}</span>
                </div>
            </a>

            <nav class="hidden items-center gap-1 md:flex" aria-label="{{ __('Page sections') }}">
                <flux:button href="#features" variant="ghost" size="sm" class="!text-zinc-300">{{ __('Product') }}</flux:button>
                <flux:button href="#platform" variant="ghost" size="sm" class="!text-zinc-300">{{ __('Platform') }}</flux:button>
                <flux:button href="#how-it-works" variant="ghost" size="sm" class="!text-zinc-300">{{ __('How it works') }}</flux:button>
                <flux:button href="#audience" variant="ghost" size="sm" class="!text-zinc-300">{{ __('Who it’s for') }}</flux:button>
            </nav>

            <div class="hidden items-center gap-2 md:flex">
                <flux:button :href="route('customer.login')" variant="ghost" size="sm" icon="user-circle" class="!text-zinc-200" wire:navigate>
                    {{ __('Customer portal') }}
                </flux:button>
                <flux:button :href="route('customer.register')" variant="primary" size="sm" icon="sparkles" wire:navigate>
                    {{ __('Get started') }}
                </flux:button>
                <flux:button :href="route('login')" variant="outline" size="sm" icon="shield-check" class="border-white/20 !text-white hover:bg-white/10" wire:navigate>
                    {{ __('Admin') }}
                </flux:button>
            </div>

            <flux:button
                type="button"
                variant="ghost"
                class="md:hidden !text-zinc-200"
                icon="bars-3"
                wire:click="$toggle('mobileOpen')"
                :aria-expanded="$mobileOpen"
                aria-controls="welcome-mobile-nav"
            />
        </header>

        <div
            id="welcome-mobile-nav"
            @class([
                'border-t border-white/10 bg-zinc-950/98 md:hidden',
                'hidden' => ! $mobileOpen,
            ])
        >
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-4 sm:px-6">
                <flux:button href="#features" variant="ghost" class="w-full justify-start !text-zinc-200" wire:click="$set('mobileOpen', false)">{{ __('Product') }}</flux:button>
                <flux:button href="#platform" variant="ghost" class="w-full justify-start !text-zinc-200" wire:click="$set('mobileOpen', false)">{{ __('Platform') }}</flux:button>
                <flux:button href="#how-it-works" variant="ghost" class="w-full justify-start !text-zinc-200" wire:click="$set('mobileOpen', false)">{{ __('How it works') }}</flux:button>
                <flux:button href="#audience" variant="ghost" class="w-full justify-start !text-zinc-200" wire:click="$set('mobileOpen', false)">{{ __('Who it’s for') }}</flux:button>
                <flux:separator class="my-1" />
                <flux:button :href="route('customer.login')" variant="ghost" class="w-full justify-start" icon="user-circle" wire:navigate wire:click="$set('mobileOpen', false)">{{ __('Customer login') }}</flux:button>
                <flux:button :href="route('customer.register')" variant="primary" class="w-full justify-center" wire:navigate wire:click="$set('mobileOpen', false)">{{ __('Create account') }}</flux:button>
                <flux:button :href="route('login')" variant="outline" class="w-full justify-center border-white/20 !text-white" wire:navigate wire:click="$set('mobileOpen', false)">{{ __('Admin login') }}</flux:button>
            </div>
        </div>
    </div>

    {{-- ── Hero ─────────────────────────────────────────────────────────── --}}
    <div class="dark relative overflow-hidden bg-zinc-950">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -right-40 -top-40 size-[520px] rounded-full bg-sky-600/25 blur-3xl"></div>
            <div class="absolute -bottom-44 -left-44 size-[480px] rounded-full bg-violet-600/20 blur-3xl"></div>
            <div class="absolute inset-0 opacity-[0.35]" style="background-image:radial-gradient(circle,rgba(148,163,184,.06) 1px,transparent 1px);background-size:28px 28px"></div>
        </div>

        <div class="relative z-10 mx-auto max-w-7xl px-4 pb-20 pt-16 sm:px-6 lg:px-8 lg:pb-28 lg:pt-24">
            <div class="mb-8 flex justify-center">
                <flux:badge color="sky" size="sm" class="!border-sky-500/30 !bg-sky-500/10 !text-sky-300">
                    {{ __('Built for ISPs, resellers & serious hotspot operators in East Africa') }}
                </flux:badge>
            </div>

            <div class="mx-auto max-w-4xl text-center">
                <flux:heading size="xl" level="1" class="text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl lg:!leading-[1.08]">
                    {{ __('Run WiFi billing, routers, and payouts') }}
                    <span class="mt-2 block bg-gradient-to-r from-sky-300 to-cyan-200 bg-clip-text text-transparent">{{ __('on Autopilot') }}</span>
                </flux:heading>

                <flux:text class="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-zinc-400 sm:text-lg">
                    {{ __('SKYmanager is a full operations layer for MikroTik hotspots: captive portal bundles, per-customer mobile money (ClickPesa), subscriptions & invoices, router health, and admin reporting — not just a login page.') }}
                </flux:text>
                <p class="mx-auto mt-3 max-w-2xl text-sm text-zinc-500">
                    {{ __('Jukwaa kamili la biashara ya WiFi na malipo ya kidijitali — limeundwa kwa ukweli wa uendeshaji wa kila siku.') }}
                </p>

                <div class="mt-10 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                    <flux:button :href="route('customer.register')" variant="primary" size="base" icon="rocket-launch" class="min-h-12 !px-6 text-base shadow-lg shadow-sky-500/25" wire:navigate>
                        {{ __('Create free account') }}
                    </flux:button>
                    <flux:button :href="route('customer.login')" variant="outline" size="base" class="min-h-12 !px-6 text-base border-white/25 !text-white hover:bg-white/10" wire:navigate>
                        {{ __('Customer sign in') }}
                    </flux:button>
                </div>

                <p class="mt-4 text-sm text-zinc-500">
                    {{ __('Platform team?') }}
                    <a href="{{ route('login') }}" wire:navigate class="font-medium text-zinc-300 underline decoration-zinc-600 underline-offset-4 hover:text-white">{{ __('Open admin console') }}</a>
                </p>
            </div>

            {{-- Product preview card --}}
            <div class="mx-auto mt-16 max-w-5xl">
                <flux:card class="overflow-hidden !border-white/10 !bg-zinc-900/80 !p-0 shadow-2xl shadow-black/40 ring-1 ring-white/5 backdrop-blur">
                    <div class="flex items-center gap-2 border-b border-white/10 bg-zinc-800/90 px-4 py-3">
                        <div class="size-2.5 rounded-full bg-red-500/90"></div>
                        <div class="size-2.5 rounded-full bg-amber-400/90"></div>
                        <div class="size-2.5 rounded-full bg-emerald-400/90"></div>
                        <div class="ml-3 flex-1 rounded-md bg-zinc-950/60 px-3 py-1 font-mono text-xs text-zinc-500">
                            {{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'app' }}/customer/dashboard
                        </div>
                    </div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4 sm:p-6">
                        @foreach ([
                            ['label' => __('Routers online'), 'value' => '12', 'icon' => 'signal', 'tone' => 'sky'],
                            ['label' => __('Revenue (30d)'), 'value' => 'TZS 320K', 'icon' => 'banknotes', 'tone' => 'emerald'],
                            ['label' => __('Portal sessions'), 'value' => '247', 'icon' => 'users', 'tone' => 'violet'],
                            ['label' => __('Payments'), 'value' => __('Healthy'), 'icon' => 'check-badge', 'tone' => 'lime'],
                        ] as $stat)
                            <div class="rounded-xl border border-white/5 bg-zinc-950/50 p-4">
                                <div class="mb-2 flex items-center gap-2">
                                    <div @class([
                                        'flex size-8 items-center justify-center rounded-lg',
                                        'bg-sky-500/15 text-sky-400' => $stat['tone'] === 'sky',
                                        'bg-emerald-500/15 text-emerald-400' => $stat['tone'] === 'emerald',
                                        'bg-violet-500/15 text-violet-400' => $stat['tone'] === 'violet',
                                        'bg-lime-500/15 text-lime-400' => $stat['tone'] === 'lime',
                                    ])>
                                        <flux:icon :name="$stat['icon']" class="size-4 shrink-0" />
                                    </div>
                                    <flux:text class="text-xs text-zinc-500">{{ $stat['label'] }}</flux:text>
                                </div>
                                <p class="text-lg font-semibold text-white">{{ $stat['value'] }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="border-t border-white/5 bg-zinc-950/40 px-5 py-4 sm:px-6">
                        <div class="flex h-16 items-end gap-1">
                            @foreach ([42, 65, 48, 80, 55, 90, 70, 85, 60, 95, 75, 88] as $h)
                                <div class="flex-1 rounded-t-sm bg-sky-500/40" style="height: {{ $h }}%"></div>
                            @endforeach
                        </div>
                        <flux:text class="mt-2 block text-center text-xs text-zinc-500">{{ __('Illustrative dashboard — your numbers, live') }}</flux:text>
                    </div>
                </flux:card>
                <div class="mx-auto mt-4 h-6 w-2/3 rounded-full bg-sky-500/10 blur-2xl"></div>
            </div>
        </div>
    </div>

    {{-- ── Platform pillars ─────────────────────────────────────────────── --}}
    <section id="platform" class="border-b border-zinc-200 bg-white py-20 dark:border-zinc-800 dark:bg-zinc-950 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <flux:badge color="zinc" class="mb-4">{{ __('One platform, three audiences') }}</flux:badge>
                <flux:heading size="xl" class="text-zinc-900 dark:text-white">{{ __('Customers, resellers, and your control room') }}</flux:heading>
                <flux:text class="mt-4 text-zinc-600 dark:text-zinc-400">
                    {{ __('Hotspot owners run their business in the customer portal. Partners see a scoped portfolio. Admins retain full visibility, repair tools, exports, and audit trails.') }}
                </flux:text>
            </div>
            <div class="mt-12 grid gap-6 md:grid-cols-3">
                @foreach ([
                    ['title' => __('Customer portal'), 'body' => __('Claim routers, manage plans & vouchers, ClickPesa credentials, invoices, and operational health — without calling support.')],
                    ['title' => __('Reseller / partner ops'), 'body' => __('Portfolio summaries, revenue-friendly reporting, and incident visibility scoped to the networks you manage.')],
                    ['title' => __('Admin & finance'), 'body' => __('Router operations, payment support, CSV exports, subscription revenue views, and activity logging for compliance.')],
                ] as $pillar)
                    <flux:card class="h-full !p-6">
                        <flux:heading size="lg" class="text-zinc-900 dark:text-white">{{ $pillar['title'] }}</flux:heading>
                        <flux:text class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $pillar['body'] }}</flux:text>
                    </flux:card>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Features ───────────────────────────────────────────────────────── --}}
    <section id="features" class="border-b border-zinc-200 bg-zinc-50 py-20 dark:border-zinc-800 dark:bg-zinc-900 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <flux:badge color="sky">{{ __('Capabilities') }}</flux:badge>
                <flux:heading size="xl" class="mt-4 text-zinc-900 dark:text-white">{{ __('Everything a modern WiFi operator expects') }}</flux:heading>
                <flux:text class="mt-4 text-zinc-600 dark:text-zinc-400">
                    {{ __('From the captive portal bundle to authorization retries — designed for real routers and real payments.') }}
                </flux:text>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['icon' => 'server-stack', 'title' => __('MikroTik & hotspot bundle'), 'body' => __('Popup-safe captive portal assets, API provisioning patterns, and router lifecycle aligned with how MikroTik actually behaves.')],
                    ['icon' => 'currency-dollar', 'title' => __('Per-customer ClickPesa'), 'body' => __('Each operator uses their own merchant rails. Credentials are encrypted; payouts stay in your ecosystem, not a black box.')],
                    ['icon' => 'window', 'title' => __('Plans, vouchers & portal'), 'body' => __('Sell time and data products, track voucher inventory, and give subscribers a clear path from pay to online.')],
                    ['icon' => 'chart-bar', 'title' => __('Reporting & exports'), 'body' => __('Revenue, hotspot payments, router health snapshots, incidents, and invoices — filter by date, export CSV where it matters.')],
                    ['icon' => 'document-text', 'title' => __('Invoices & billing history'), 'body' => __('Professional records customers can trust: status, dates, downloads, and a path from payment to invoice.')],
                    ['icon' => 'lock-closed', 'title' => __('Security & tenancy'), 'body' => __('Role-based access, separate customer vs staff auth, and data scoped so tenants never leak across accounts.')],
                ] as $f)
                    <flux:card class="group h-full !p-6 transition hover:-translate-y-0.5 hover:shadow-md dark:hover:shadow-none">
                        <div class="mb-4 inline-flex size-12 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400">
                            <flux:icon :name="$f['icon']" class="size-6" />
                        </div>
                        <flux:heading size="lg" class="text-zinc-900 dark:text-white">{{ $f['title'] }}</flux:heading>
                        <flux:text class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $f['body'] }}</flux:text>
                    </flux:card>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── How it works ───────────────────────────────────────────────────── --}}
    <section id="how-it-works" class="border-b border-zinc-200 bg-white py-20 dark:border-zinc-800 dark:bg-zinc-950 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <flux:badge color="violet">{{ __('Fast setup') }}</flux:badge>
                <flux:heading size="xl" class="mt-4 text-zinc-900 dark:text-white">{{ __('Live in three calm steps') }}</flux:heading>
                <flux:text class="mt-4 text-zinc-600 dark:text-zinc-400">{{ __('No slide-deck promises — a straight path from account to paid sessions.') }}</flux:text>
            </div>
            <div class="mt-14 grid gap-8 md:grid-cols-3">
                @foreach ([
                    ['n' => '1', 'title' => __('Register & claim a router'), 'body' => __('Create your customer account, claim the router by identity you already trust (e.g. MAC), and see it in your operations view.')],
                    ['n' => '2', 'title' => __('Connect payments & portal'), 'body' => __('Link ClickPesa, publish plans, and deploy the hotspot bundle so browsers open your portal reliably.')],
                    ['n' => '3', 'title' => __('Sell access, monitor health'), 'body' => __('Guests pay, get online, and you watch authorizations, failures, retries, and recovery from one honest dashboard.')],
                ] as $step)
                    <flux:card class="relative !p-8 text-center">
                        <div class="absolute -right-1 -top-1 flex size-8 items-center justify-center rounded-full border-2 border-white bg-sky-500 text-xs font-bold text-white dark:border-zinc-900">
                            {{ $step['n'] }}
                        </div>
                        <flux:heading size="lg" class="text-zinc-900 dark:text-white">{{ $step['title'] }}</flux:heading>
                        <flux:text class="mt-3 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $step['body'] }}</flux:text>
                    </flux:card>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Audience ───────────────────────────────────────────────────────── --}}
    <section id="audience" class="border-b border-zinc-200 bg-zinc-50 py-20 dark:border-zinc-800 dark:bg-zinc-900 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <flux:heading size="xl" class="text-zinc-900 dark:text-white">{{ __('Trusted from cafés to campus networks') }}</flux:heading>
                <flux:text class="mt-4 text-zinc-600 dark:text-zinc-400">{{ __('Whether you run one site or many, the same operational spine keeps billing honest.') }}</flux:text>
            </div>
            <div class="mt-12 grid gap-6 md:grid-cols-3">
                @foreach ([
                    ['icon' => 'cake', 'title' => __('Hospitality & retail'), 'body' => __('Turn WiFi into a metered product: hourly passes, fair-use caps, and receipts customers understand.')],
                    ['icon' => 'home-modern', 'title' => __('Community ISPs & estates'), 'body' => __('Many routers, clear ownership, and support workflows that do not depend on one engineer’s laptop.')],
                    ['icon' => 'building-office-2', 'title' => __('Campus & enterprise'), 'body' => __('Governed access, audit-friendly logs, and finance-ready exports for the back office.')],
                ] as $aud)
                    <flux:card class="flex h-full flex-col !p-8">
                        <flux:icon :name="$aud['icon']" class="mb-4 size-10 text-amber-500 dark:text-amber-400" />
                        <flux:heading size="lg" class="text-zinc-900 dark:text-white">{{ $aud['title'] }}</flux:heading>
                        <flux:text class="mt-2 flex-1 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $aud['body'] }}</flux:text>
                        <flux:button :href="route('customer.register')" variant="ghost" class="mt-6 justify-start px-0 !text-sky-600 dark:!text-sky-400" icon="arrow-right" wire:navigate>
                            {{ __('Start with SKYmanager') }}
                        </flux:button>
                    </flux:card>
                @endforeach
            </div>

            <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    __('Encrypted credentials & sane secrets handling'),
                    __('Live router signals, not vanity charts'),
                    __('Payment funnel you can explain to finance'),
                    __('Operational exports for support teams'),
                ] as $trust)
                    <flux:callout variant="secondary" icon="check-circle" class="!py-3">
                        {{ $trust }}
                    </flux:callout>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Dual CTA ───────────────────────────────────────────────────────── --}}
    <section class="dark bg-zinc-950 py-20 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-2">
                <flux:card class="relative overflow-hidden !border-sky-500/25 !bg-gradient-to-br from-sky-500/10 to-zinc-950 !p-8 sm:!p-10">
                    <div class="pointer-events-none absolute -right-16 -top-16 size-48 rounded-full bg-sky-500/20 blur-2xl"></div>
                    <flux:badge color="sky" class="mb-4">{{ __('Hotspot owners') }}</flux:badge>
                    <flux:heading size="xl" class="text-white">{{ __('Start selling WiFi access today') }}</flux:heading>
                    <flux:text class="mt-3 text-zinc-400">{{ __('Self-serve onboarding, your own payment identity, and a portal your guests will actually finish.') }}</flux:text>
                    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                        <flux:button :href="route('customer.register')" variant="primary" icon="sparkles" wire:navigate>{{ __('Create free account') }}</flux:button>
                        <flux:button :href="route('customer.login')" variant="ghost" class="!text-white" wire:navigate>{{ __('I already have an account') }}</flux:button>
                    </div>
                </flux:card>
                <flux:card class="relative overflow-hidden !border-violet-500/25 !bg-gradient-to-br from-violet-500/10 to-zinc-950 !p-8 sm:!p-10">
                    <div class="pointer-events-none absolute -right-16 -top-16 size-48 rounded-full bg-violet-500/20 blur-2xl"></div>
                    <flux:badge color="zinc" class="mb-4 border-violet-500/30 !bg-violet-500/10 !text-violet-300">{{ __('Platform administrators') }}</flux:badge>
                    <flux:heading size="xl" class="text-white">{{ __('Operate the whole estate with confidence') }}</flux:heading>
                    <flux:text class="mt-3 text-zinc-400">{{ __('Router repairs, payment support, exports, and the audit trail your compliance conversations need.') }}</flux:text>
                    <div class="mt-6">
                        <flux:button :href="route('login')" variant="primary" icon="shield-check" class="!bg-violet-600 hover:!bg-violet-500" wire:navigate>{{ __('Admin console login') }}</flux:button>
                    </div>
                    <flux:text class="mt-4 text-xs text-zinc-600">{{ __('Fortify session · role-based access') }}</flux:text>
                </flux:card>
            </div>
        </div>
    </section>

    {{-- ── Footer ─────────────────────────────────────────────────────────── --}}
    <footer class="border-t border-white/10 bg-zinc-900">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="flex flex-col items-center justify-between gap-6 sm:flex-row sm:items-start">
                <div class="flex flex-col items-center gap-2 sm:items-start">
                    <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2 text-lg font-semibold text-white">
                        <span class="flex size-8 items-center justify-center rounded-lg bg-sky-500">
                            <flux:icon name="wifi" class="size-4 text-white" />
                        </span>
                        {{ $companyName }}
                    </a>
                    <flux:text class="text-center text-xs text-zinc-500 sm:text-start">{{ __('WiFi billing, router operations, and customer trust — in one SaaS spine.') }}</flux:text>
                </div>
                <nav class="flex flex-wrap items-center justify-center gap-4 text-sm text-zinc-500">
                    <a href="{{ route('customer.login') }}" wire:navigate class="transition hover:text-white">{{ __('Customer login') }}</a>
                    <a href="{{ route('customer.register') }}" wire:navigate class="transition hover:text-white">{{ __('Register') }}</a>
                    <a href="{{ route('login') }}" wire:navigate class="transition hover:text-white">{{ __('Admin') }}</a>
                </nav>
            </div>
            <flux:separator class="my-8" />
            <div class="flex flex-col items-center justify-between gap-3 text-xs text-zinc-600 sm:flex-row">
                <span>&copy; {{ date('Y') }} {{ $companyName }}. {{ __('All rights reserved.') }}</span>
                <span>{{ __('SKYmanager — serious WiFi operations') }}</span>
            </div>
        </div>
    </footer>

</div>
