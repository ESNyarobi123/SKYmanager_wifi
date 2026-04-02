<div>

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">{{ __('Referral Program') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-neutral-500">
            {{ __('Invite friends and earn') }} <strong class="text-gray-700 dark:text-neutral-300">{{ $this->rewardDays }} {{ trans_choice('day|days', $this->rewardDays) }}</strong>
            {{ __('of free internet for every successful sign-up.') }}
        </p>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 mb-6">
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-sky-100 dark:bg-sky-800/30">
                    <x-lucide name="users" class="size-5 text-sky-600 dark:text-sky-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">{{ __('Total') }}</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 dark:text-neutral-200">{{ $this->referrals->count() }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-neutral-500">{{ __('Referrals') }}</p>
        </div>
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-emerald-100 dark:bg-emerald-800/30">
                    <x-lucide name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">{{ __('Applied') }}</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 dark:text-neutral-200">{{ $this->appliedCount }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-neutral-500">{{ __('Rewards') }}</p>
        </div>
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-amber-100 dark:bg-amber-800/30">
                    <x-lucide name="clock" class="size-5 text-amber-600 dark:text-amber-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">{{ __('Pending') }}</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 dark:text-neutral-200">{{ $this->pendingCount }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-neutral-500">{{ __('Awaiting') }}</p>
        </div>
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-violet-100 dark:bg-violet-800/30">
                    <x-lucide name="gift" class="size-5 text-violet-600 dark:text-violet-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">{{ __('Free Days') }}</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 dark:text-neutral-200">{{ $this->appliedCount * $this->rewardDays }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-neutral-500">{{ __('Earned') }}</p>
        </div>
    </div>

    {{-- Referral Link Card --}}
    @if($this->customer->referral_code)
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-6 mb-6"
             x-data="{ copied: false }">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-neutral-200 mb-1">{{ __('Your Referral Link') }}</h2>
            <p class="text-sm text-gray-500 dark:text-neutral-500 mb-4">
                {{ __('Share this link. When someone signs up, you both get') }}
                <strong class="text-gray-700 dark:text-neutral-300">{{ $this->rewardDays }} {{ trans_choice('day|days', $this->rewardDays) }}</strong> {{ __('free.') }}
            </p>

            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1 rounded-lg bg-gray-50 dark:bg-neutral-700 border border-gray-200 dark:border-neutral-600 px-4 py-3 font-mono text-sm text-sky-600 dark:text-sky-400 truncate">
                    {{ $this->referralLink }}
                </div>
                <button
                    x-on:click="navigator.clipboard.writeText('{{ $this->referralLink }}').then(() => { copied = true; setTimeout(() => copied = false, 2500); })"
                    class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-medium transition-all"
                    :class="copied
                        ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-700'
                        : 'bg-sky-600 hover:bg-sky-700 text-white border border-sky-600'"
                >
                    <svg x-show="!copied" class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                    <svg x-show="copied" class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy Link') }}'"></span>
                </button>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <p class="text-sm text-gray-500 dark:text-neutral-500">{{ __('Your code:') }}</p>
                <span class="rounded-lg bg-sky-100 dark:bg-sky-800/40 border border-sky-200 dark:border-sky-700 px-3 py-1 font-mono text-sm font-bold text-sky-700 dark:text-sky-300">
                    {{ $this->customer->referral_code }}
                </span>
            </div>
        </div>
    @else
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 dark:bg-amber-800/10 dark:border-amber-900 dark:text-amber-500 mb-6" role="alert">
            <div class="flex gap-x-3">
                <x-lucide name="clock" class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5"/>
                <p class="text-sm">{{ __('Your referral code is being generated. Please refresh the page in a moment.') }}</p>
            </div>
        </div>
    @endif

    {{-- Referral History --}}
    <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-neutral-700">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-neutral-200 flex items-center gap-2">
                <x-lucide name="users" class="size-4 text-sky-500"/>
                {{ __('People You Referred') }}
            </h2>
        </div>
        @if($this->referrals->isNotEmpty())
            <div class="divide-y divide-gray-100 dark:divide-neutral-700">
                @foreach($this->referrals as $referral)
                    <div class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 dark:hover:bg-neutral-700/30 transition-colors">
                        <div class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-neutral-700 text-xs font-bold text-gray-600 dark:text-neutral-300">
                            {{ $referral->referred?->initials() ?? '?' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 dark:text-neutral-200 truncate">
                                {{ $referral->referred?->name ?? __('Unknown') }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-neutral-500">
                                {{ __('Joined') }} {{ $referral->created_at->format('d M Y') }}
                            </p>
                        </div>
                        <div>
                            @if($referral->isApplied())
                                <flux:badge color="green" size="sm">{{ __('Reward Applied') }} (+{{ $referral->reward_days }}d)</flux:badge>
                            @else
                                <flux:badge color="yellow" size="sm">{{ __('Pending') }}</flux:badge>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <div class="inline-flex items-center justify-center size-14 rounded-2xl bg-gray-100 dark:bg-neutral-700 mb-4">
                    <x-lucide name="user-plus" class="size-7 text-gray-400 dark:text-neutral-500"/>
                </div>
                <p class="text-sm font-medium text-gray-500 dark:text-neutral-400">{{ __('No referrals yet') }}</p>
                <p class="text-xs text-gray-400 dark:text-neutral-500 mt-1">{{ __('Share your link above to start earning free days.') }}</p>
            </div>
        @endif
    </div>

</div>
