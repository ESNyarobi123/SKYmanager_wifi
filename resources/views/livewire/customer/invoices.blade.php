<div>

    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">{{ __('Invoices') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-neutral-500">{{ __('Download and track your billing history') }}</p>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-sky-100 dark:bg-sky-800/30">
                    <x-lucide name="file-text" class="size-5 text-sky-600 dark:text-sky-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">{{ __('Total Invoices') }}</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 dark:text-neutral-200">{{ $this->invoiceCount }}</p>
        </div>
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-teal-100 dark:bg-teal-800/30">
                    <x-lucide name="bar-chart-3" class="size-5 text-teal-600 dark:text-teal-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">{{ __('Total Paid') }}</span>
            </div>
            <p class="text-2xl font-bold text-gray-800 dark:text-neutral-200">TZS {{ number_format($this->totalPaid) }}</p>
        </div>
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-amber-100 dark:bg-amber-800/30">
                    <x-lucide name="clock" class="size-5 text-amber-600 dark:text-amber-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">{{ __('Pending') }}</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 dark:text-neutral-200">{{ $this->invoices->where('status', 'issued')->count() }}</p>
        </div>
    </div>

    {{-- Invoice List --}}
    <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-5 py-4 border-b border-gray-100 dark:border-neutral-700">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-neutral-200 flex items-center gap-2">
                <x-lucide name="file-text" class="size-4 text-sky-500"/>
                {{ __('Invoice History') }}
            </h2>
            <flux:select wire:model.live="statusFilter" size="sm" class="w-full sm:w-40">
                <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                <flux:select.option value="paid">{{ __('Paid') }}</flux:select.option>
                <flux:select.option value="issued">{{ __('Issued') }}</flux:select.option>
                <flux:select.option value="void">{{ __('Void') }}</flux:select.option>
            </flux:select>
        </div>

        @if($this->invoices->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-700/30">
                        <tr>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Invoice #') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Plan') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Router') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Status') }}</th>
                            <th class="px-5 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Date') }}</th>
                            <th class="px-5 py-3 text-end text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                        @foreach($this->invoices as $invoice)
                            <tr class="hover:bg-gray-50 dark:hover:bg-neutral-700/20 transition-colors">
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <span class="font-mono text-xs font-semibold text-sky-600 dark:text-sky-400">{{ $invoice->invoice_number }}</span>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-neutral-200">
                                    {{ $invoice->subscription?->plan?->name ?? '—' }}
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                                    {{ $invoice->subscription?->router?->name ?? '—' }}
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm font-semibold text-gray-800 dark:text-neutral-200">
                                    {{ $invoice->currency }} {{ number_format((float) $invoice->total) }}
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    @php $colors = ['paid' => 'green', 'issued' => 'yellow', 'void' => 'zinc', 'draft' => 'zinc']; @endphp
                                    <flux:badge color="{{ $colors[$invoice->status] ?? 'zinc' }}" size="sm">
                                        {{ ucfirst($invoice->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-xs text-gray-400 dark:text-neutral-500">
                                    {{ $invoice->issued_at?->format('d M Y') ?? $invoice->created_at->format('d M Y') }}
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-end">
                                    <a href="{{ route('customer.invoices.download', $invoice) }}" target="_blank"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-neutral-600 bg-white dark:bg-neutral-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-neutral-300 hover:border-sky-400 hover:text-sky-600 dark:hover:text-sky-400 transition-colors">
                                        <x-lucide name="download" class="size-3.5"/>
                                        {{ __('PDF') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="flex flex-col items-center justify-center py-14 text-center">
                <div class="inline-flex items-center justify-center size-14 rounded-2xl bg-gray-100 dark:bg-neutral-700 mb-4">
                    <x-lucide name="file-text" class="size-7 text-gray-400 dark:text-neutral-500"/>
                </div>
                <p class="text-sm font-medium text-gray-500 dark:text-neutral-400">{{ __('No invoices yet') }}</p>
                <p class="text-xs text-gray-400 dark:text-neutral-500 mt-1">{{ __('Invoices appear here after successful payments.') }}</p>
            </div>
        @endif
    </div>

</div>
