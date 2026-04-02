<div>

    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">{{ __('Subscriptions & Payments') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-neutral-500">
            {{ __('Track your active plans and payment history') }}
        </p>
    </div>

    {{-- Summary Stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-emerald-100 dark:bg-emerald-800/30">
                    <x-lucide name="signal" class="size-5 text-emerald-600 dark:text-emerald-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">{{ __('Active Plans') }}</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 dark:text-neutral-200">{{ $this->subscriptions->where('status', 'active')->count() }}</p>
        </div>
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-sky-100 dark:bg-sky-800/30">
                    <x-lucide name="credit-card" class="size-5 text-sky-600 dark:text-sky-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">{{ __('Total Subscriptions') }}</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 dark:text-neutral-200">{{ $this->subscriptions->count() }}</p>
        </div>
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-teal-100 dark:bg-teal-800/30">
                    <x-lucide name="bar-chart-3" class="size-5 text-teal-600 dark:text-teal-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">{{ __('Total Paid') }}</span>
            </div>
            <p class="text-2xl font-bold text-gray-800 dark:text-neutral-200">TZS {{ number_format($this->totalSpend) }}</p>
        </div>
    </div>

    {{-- Subscriptions Table --}}
    <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 mb-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-5 py-4 border-b border-gray-100 dark:border-neutral-700">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-neutral-200 flex items-center gap-2">
                <x-lucide name="credit-card" class="size-4 text-sky-500"/>
                {{ __('Subscriptions') }}
            </h2>
            <flux:select wire:model.live="statusFilter" size="sm" class="w-full sm:w-40">
                <flux:select.option value="all">{{ __('All statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="expired">{{ __('Expired') }}</flux:select.option>
                <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
            </flux:select>
        </div>

        <div class="overflow-x-auto">
            @if($this->subscriptions->isNotEmpty())
                <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-700/30">
                        <tr>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Plan') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Router') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Expires') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Data Used') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                        @foreach($this->subscriptions as $sub)
                            <tr class="hover:bg-gray-50 dark:hover:bg-neutral-700/20 transition-colors">
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <p class="text-sm font-medium text-gray-800 dark:text-neutral-200">{{ $sub->plan->name ?? '—' }}</p>
                                    @if($sub->plan)
                                        <p class="text-xs text-gray-400 dark:text-neutral-500 mt-0.5">
                                            {{ $sub->plan->upload_limit }}Mbps ↑ / {{ $sub->plan->download_limit }}Mbps ↓
                                        </p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">{{ $sub->router->name ?? '—' }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    @if($sub->expires_at)
                                        <p class="text-sm text-gray-800 dark:text-neutral-200">{{ $sub->expires_at->format('d M Y') }}</p>
                                        <p class="text-xs {{ $sub->expires_at->isPast() ? 'text-red-500' : 'text-gray-400 dark:text-neutral-500' }}">
                                            {{ $sub->expires_at->diffForHumans() }}
                                        </p>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                                    @if($sub->data_used_mb)
                                        {{ number_format($sub->data_used_mb) }} MB
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <flux:badge color="{{ $sub->status === 'active' ? 'green' : ($sub->status === 'pending' ? 'yellow' : 'zinc') }}" size="sm">
                                        {{ ucfirst($sub->status) }}
                                    </flux:badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="inline-flex items-center justify-center size-12 rounded-xl bg-gray-100 dark:bg-neutral-700 mb-3">
                        <x-lucide name="credit-card" class="size-6 text-gray-400 dark:text-neutral-500"/>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-neutral-400">{{ __('No subscriptions found') }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Payment History --}}
    <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-neutral-700">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-neutral-200 flex items-center gap-2">
                <x-lucide name="bar-chart-3" class="size-4 text-teal-500"/>
                {{ __('Payment History') }}
            </h2>
        </div>
        <div class="overflow-x-auto">
            @if($this->recentPayments->isNotEmpty())
                <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-700/30">
                        <tr>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Reference') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Plan') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Router') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Provider') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Status') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                        @foreach($this->recentPayments as $payment)
                            <tr class="hover:bg-gray-50 dark:hover:bg-neutral-700/20 transition-colors">
                                <td class="px-5 py-3 whitespace-nowrap font-mono text-xs text-gray-500 dark:text-neutral-400">
                                    {{ $payment->reference ?? $payment->transaction_id ?? '—' }}
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-neutral-200">
                                    {{ $payment->subscription->plan->name ?? '—' }}
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                                    {{ $payment->subscription->router->name ?? '—' }}
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm font-semibold text-gray-800 dark:text-neutral-200">
                                    TZS {{ number_format($payment->amount) }}
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400 capitalize">
                                    {{ $payment->provider ?? '—' }}
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <flux:badge color="{{ $payment->status === 'paid' ? 'green' : ($payment->status === 'pending' ? 'yellow' : 'red') }}" size="sm">
                                        {{ ucfirst($payment->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-400 dark:text-neutral-500">{{ $payment->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="inline-flex items-center justify-center size-12 rounded-xl bg-gray-100 dark:bg-neutral-700 mb-3">
                        <x-lucide name="bar-chart-3" class="size-6 text-gray-400 dark:text-neutral-500"/>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-neutral-400">{{ __('No payment history yet') }}</p>
                </div>
            @endif
        </div>
    </div>

</div>
