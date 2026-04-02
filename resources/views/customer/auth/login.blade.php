<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased" style="background: linear-gradient(135deg, #0c4a6e 0%, #0369a1 50%, #075985 100%);">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-6">

                <div class="flex flex-col items-center gap-3">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/20 shadow-xl">
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                    </div>
                    <div class="text-center">
                        <h1 class="text-2xl font-bold text-white tracking-tight">SKYmanager</h1>
                        <p class="text-sm text-sky-200 mt-0.5">Customer Portal</p>
                    </div>
                </div>

                <div class="rounded-2xl bg-white/10 backdrop-blur border border-white/15 shadow-2xl p-8">
                    <div class="flex flex-col gap-6 [&_label]:text-sky-100 [&_input]:bg-white/10 [&_input]:border-white/20 [&_input]:text-white [&_input::placeholder]:text-sky-300 [&_input:focus]:border-sky-300 [&_input:focus]:ring-sky-400/30 [&_a]:text-sky-200 [&_a:hover]:text-white">

                        <div class="text-center">
                            <h2 class="text-xl font-semibold text-white">{{ __('Welcome back') }}</h2>
                            <p class="text-sm text-sky-200 mt-1">{{ __('Sign in to your customer account') }}</p>
                        </div>

                        @if ($errors->any())
                            <div class="rounded-lg bg-red-500/20 border border-red-400/30 px-4 py-3">
                                @foreach ($errors->all() as $error)
                                    <p class="text-sm text-red-200">{{ $error }}</p>
                                @endforeach
                            </div>
                        @endif

                        @if (session('status'))
                            <div class="rounded-lg bg-green-500/20 border border-green-400/30 px-4 py-3">
                                <p class="text-sm text-green-200">{{ session('status') }}</p>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('customer.login.store') }}" class="flex flex-col gap-4">
                            @csrf

                            <flux:input
                                name="phone"
                                :label="__('Phone Number')"
                                :value="old('phone')"
                                type="text"
                                required
                                autofocus
                                autocomplete="tel"
                                placeholder="255712345678"
                            />

                            <div class="relative">
                                <flux:input
                                    name="password"
                                    :label="__('Password')"
                                    type="password"
                                    required
                                    autocomplete="current-password"
                                    :placeholder="__('Password')"
                                    viewable
                                />
                            </div>

                            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

                            <flux:button variant="primary" type="submit" class="w-full bg-sky-500 hover:bg-sky-400 border-sky-400 mt-2">
                                {{ __('Sign In') }}
                            </flux:button>
                        </form>

                        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-sky-300">
                            <span>{{ __("Don't have an account?") }}</span>
                            <a href="{{ route('customer.register') }}" class="font-medium underline">{{ __('Register here') }}</a>
                        </div>
                    </div>
                </div>

                <p class="text-center text-xs text-sky-300/60">
                    &copy; {{ date('Y') }} SKYmanager &mdash; Customer Portal
                </p>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
