<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased" style="background: linear-gradient(135deg, #2d0057 0%, #4b0082 50%, #1a0033 100%);">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-6">

                {{-- Brand Header --}}
                <div class="flex flex-col items-center gap-3">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/20 shadow-xl">
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                    </div>
                    <div class="text-center">
                        <h1 class="text-2xl font-bold text-white tracking-tight">SKYmanager</h1>
                        <p class="text-sm text-purple-200 mt-0.5">WiFi Hotspot Management</p>
                    </div>
                </div>

                {{-- Auth Card --}}
                <div class="rounded-2xl bg-white/10 backdrop-blur border border-white/15 shadow-2xl p-8">
                    <div class="flex flex-col gap-6 [&_label]:text-purple-100 [&_input]:bg-white/10 [&_input]:border-white/20 [&_input]:text-white [&_input::placeholder]:text-purple-300 [&_input:focus]:border-purple-300 [&_input:focus]:ring-purple-400/30 [&_a]:text-purple-200 [&_a:hover]:text-white">
                        {{ $slot }}
                    </div>
                </div>

                <p class="text-center text-xs text-purple-300/60">
                    &copy; {{ date('Y') }} SKYmanager &mdash; Admin Access Only
                </p>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
