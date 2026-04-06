<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Export center') }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Download CSV snapshots for support and finance. Exports respect your role scope.') }}</p>
        </div>
        <flux:button :href="route('admin.reports')" variant="ghost" size="sm" wire:navigate>{{ __('Reports UI') }}</flux:button>
    </div>

    <div class="flex flex-wrap gap-3 items-end">
        <flux:input type="date" wire:model.live="dateFrom" :label="__('From')" class="w-44" />
        <flux:input type="date" wire:model.live="dateTo" :label="__('To')" class="w-44" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach($this->exportPresets() as $preset)
            <flux:card class="p-4 flex flex-col gap-2">
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $preset['label'] }}</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 grow">{{ $preset['description'] }}</p>
                <a href="{{ $this->href($preset['key']) }}"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-500 w-fit">
                    <x-lucide name="arrow-down-tray" class="size-4"/>
                    {{ __('Download CSV') }}
                </a>
            </flux:card>
        @endforeach
    </div>
</div>
