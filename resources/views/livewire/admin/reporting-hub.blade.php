<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Reports') }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Date-scoped operational and financial data for your scope (admin: full platform; reseller: your routers).') }}</p>
        </div>
        @can('reports.export')
            <flux:button :href="route('admin.support-exports')" variant="ghost" size="sm" wire:navigate icon="arrow-down-tray">{{ __('Export center') }}</flux:button>
        @endcan
    </div>

    <div class="flex flex-wrap gap-3 items-end">
        <flux:select wire:model.live="reportType" class="w-56">
            <flux:select.option value="revenue">{{ __('Revenue (subscriptions)') }}</flux:select.option>
            <flux:select.option value="hotspot">{{ __('Hotspot payments') }}</flux:select.option>
            <flux:select.option value="routers">{{ __('Router operations snapshot') }}</flux:select.option>
            <flux:select.option value="plans">{{ __('Plan performance') }}</flux:select.option>
            <flux:select.option value="incidents">{{ __('Support / incidents summary') }}</flux:select.option>
        </flux:select>
        <flux:input type="date" wire:model.live="dateFrom" :label="__('From')" class="w-44" />
        <flux:input type="date" wire:model.live="dateTo" :label="__('To')" class="w-44" />
        @can('reports.export')
            @php
                $exportType = match($reportType) {
                    'revenue' => 'revenue',
                    'hotspot' => 'hotspot_payments',
                    'routers' => 'router_operations',
                    'plans' => 'plan_performance',
                    'incidents' => 'support_incidents',
                    default => 'revenue',
                };
            @endphp
            <a href="{{ $this->exportUrl($exportType) }}"
                class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-500">
                <x-lucide name="arrow-down-tray" class="size-4"/>
                {{ __('Download CSV') }}
            </a>
        @endcan
    </div>

    <flux:card class="overflow-x-auto">
        @if($this->rows->isEmpty())
            <p class="text-sm text-zinc-500 py-10 text-center">{{ __('No rows for this range.') }}</p>
        @else
            @php
                $first = $this->rows->first();
                $cols = is_array($first) ? array_keys($first) : [];
            @endphp
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left text-xs uppercase text-zinc-500">
                        @foreach($cols as $col)
                            <th class="px-3 py-2 font-medium whitespace-nowrap">{{ str_replace('_', ' ', $col) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($this->rows as $row)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/40">
                            @foreach($cols as $col)
                                <td class="px-3 py-2 text-zinc-800 dark:text-zinc-200 max-w-xs truncate" title="{{ is_scalar($row[$col] ?? null) ? (string) ($row[$col] ?? '') : '' }}">
                                    @php $v = $row[$col] ?? '—'; @endphp
                                    {{ is_bool($v) ? ($v ? __('yes') : __('no')) : $v }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </flux:card>
</div>
