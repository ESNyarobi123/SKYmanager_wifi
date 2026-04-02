<?php

use App\Models\ActivityLog;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $causerFilter = 'all';

    public function logs()
    {
        return ActivityLog::query()
            ->when($this->search, fn ($q) => $q
                ->where('description', 'like', '%'.$this->search.'%')
                ->orWhere('ip_address', 'like', '%'.$this->search.'%'))
            ->when($this->causerFilter === 'customer', fn ($q) => $q->where('causer_type', 'App\\Models\\Customer'))
            ->when($this->causerFilter === 'admin', fn ($q) => $q->where('causer_type', 'App\\Models\\User'))
            ->when($this->causerFilter === 'system', fn ($q) => $q->whereNull('causer_type'))
            ->latest()
            ->paginate(25);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCauserFilter(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">{{ __('Activity Log') }}</h1>
            <p class="text-sm text-gray-500 dark:text-neutral-500 mt-1">{{ __('Audit trail of all important actions in the system') }}</p>
        </div>
        <span class="text-sm text-gray-400 dark:text-neutral-500">
            {{ ActivityLog::count() }} {{ __('total entries') }}
        </span>
    </div>

    <div class="flex flex-wrap gap-3 mb-4">
        <flux:input wire:model.live="search" placeholder="{{ __('Search description or IP…') }}" icon="magnifying-glass" class="max-w-xs" />
        <flux:select wire:model.live="causerFilter" class="w-36">
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            <flux:select.option value="customer">{{ __('Customers') }}</flux:select.option>
            <flux:select.option value="admin">{{ __('Admins') }}</flux:select.option>
            <flux:select.option value="system">{{ __('System') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:card class="overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column>{{ __('By') }}</flux:table.column>
                <flux:table.column>{{ __('Subject') }}</flux:table.column>
                <flux:table.column>{{ __('IP Address') }}</flux:table.column>
                <flux:table.column>{{ __('When') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->logs() as $log)
                    <flux:table.row :key="$log->id">
                        <flux:table.cell>
                            <p class="text-sm text-zinc-800 dark:text-zinc-200">{{ $log->description }}</p>
                            @if($log->properties)
                                <p class="text-xs text-zinc-400 mt-0.5 font-mono">
                                    {{ json_encode($log->properties) }}
                                </p>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($log->causer_type === 'App\\Models\\Customer')
                                <flux:badge color="sky" size="sm" icon="user">
                                    {{ class_basename($log->causer_type) }}
                                </flux:badge>
                            @elseif($log->causer_type === 'App\\Models\\User')
                                <flux:badge color="purple" size="sm" icon="shield-check">
                                    Admin
                                </flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">System</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-400 font-mono">
                            @if($log->subject_type)
                                {{ class_basename($log->subject_type) }}
                                <span class="text-zinc-300 dark:text-zinc-600">#{{ substr($log->subject_id, -8) }}</span>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-xs text-zinc-400">
                            {{ $log->ip_address ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-400" title="{{ $log->created_at->toDateTimeString() }}">
                            {{ $log->created_at->diffForHumans() }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-400 py-8">
                            {{ __('No activity logged yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">{{ $this->logs()->links() }}</div>
    </flux:card>
</div>
