<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>{{ $customer->company_name ?? $customer->name }} — WiFi Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-gradient-to-br from-sky-50 to-blue-100 dark:from-neutral-900 dark:to-neutral-800 flex flex-col items-center justify-start py-10 px-4">

    {{-- ── Brand Header ──────────────────────────────────────────────────── --}}
    <div class="w-full max-w-3xl mx-auto mb-8 text-center">
        <div class="inline-flex items-center justify-center size-14 rounded-2xl bg-sky-600 shadow-lg mb-4">
            <svg class="size-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            {{ $customer->company_name ?? $customer->name }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
            {{ __('Choose a plan to connect to the internet') }}
        </p>
    </div>

    {{-- ── Plans Grid ─────────────────────────────────────────────────────── --}}
    @if($plans->isEmpty())
    <div class="w-full max-w-sm mx-auto text-center py-16">
        <div class="inline-flex size-12 items-center justify-center rounded-full bg-sky-100 dark:bg-sky-900/30 mb-4">
            <svg class="size-6 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <p class="text-gray-500 dark:text-neutral-400 text-sm">{{ __('No plans available at the moment.') }}</p>
    </div>
    @else
    <div class="w-full max-w-3xl mx-auto grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($plans as $plan)
        <div class="flex flex-col bg-white dark:bg-neutral-800 rounded-2xl shadow-sm border border-gray-100 dark:border-neutral-700 overflow-hidden hover:shadow-md transition-shadow">

            {{-- Price band --}}
            <div class="bg-sky-600 px-5 py-4 text-center">
                <p class="text-2xl font-bold text-white">
                    TZS {{ number_format((float) $plan->price, 0) }}
                </p>
                <p class="text-sky-100 text-sm font-medium mt-0.5">{{ $plan->durationLabel() }}</p>
            </div>

            {{-- Details --}}
            <div class="flex-1 px-5 py-4 space-y-3">
                <p class="font-semibold text-gray-800 dark:text-neutral-100 text-center">{{ $plan->name }}</p>

                <div class="space-y-2">
                    {{-- Speed --}}
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-neutral-400">
                        <svg class="size-3.5 shrink-0 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span>{{ $plan->speedLabel() }}</span>
                    </div>

                    {{-- Data --}}
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-neutral-400">
                        <svg class="size-3.5 shrink-0 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <ellipse cx="12" cy="5" rx="9" ry="3"/><path stroke-linecap="round" d="M21 12c0 1.657-4.03 3-9 3S3 13.657 3 12"/><path stroke-linecap="round" d="M3 5v14c0 1.657 4.03 3 9 3s9-1.343 9-3V5"/>
                        </svg>
                        <span>
                            @if($plan->data_quota_mb)
                                @if($plan->data_quota_mb >= 1024)
                                    {{ round($plan->data_quota_mb / 1024, 1) }} GB data
                                @else
                                    {{ $plan->data_quota_mb }} MB data
                                @endif
                            @else
                                {{ __('Unlimited data') }}
                            @endif
                        </span>
                    </div>
                </div>

                @if($plan->description)
                <p class="text-xs text-gray-400 dark:text-neutral-500 line-clamp-2">{{ $plan->description }}</p>
                @endif
            </div>

            {{-- CTA --}}
            <div class="px-5 pb-5">
                <a href="{{ url('/portal?plan='.$plan->id.'&mac='.request('mac').'&ip='.request('ip').'&link-orig='.urlencode(request('link-orig', ''))) }}"
                   class="block w-full text-center rounded-xl bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white font-semibold text-sm py-3 transition-colors shadow-sm">
                    {{ __('Select Plan') }}
                </a>
            </div>

        </div>
        @endforeach
    </div>
    @endif

    {{-- ── Footer ─────────────────────────────────────────────────────────── --}}
    <p class="mt-10 text-xs text-gray-400 dark:text-neutral-600 text-center">
        Powered by <span class="font-semibold text-sky-600">SKYmanager</span>
    </p>

@fluxScripts
</body>
</html>
