<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\WifiUser;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public string $period = 'monthly';

    public function totalRevenue(): string
    {
        return number_format(
            Payment::where('status', 'success')->sum('amount'),
            0
        );
    }

    public function todayRevenue(): string
    {
        return number_format(
            Payment::where('status', 'success')->whereDate('created_at', today())->sum('amount'),
            0
        );
    }

    public function monthRevenue(): string
    {
        return number_format(
            Payment::where('status', 'success')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount'),
            0
        );
    }

    public function totalUsers(): int
    {
        return WifiUser::count();
    }

    public function activeSubscriptions(): int
    {
        return Subscription::where('status', 'active')->count();
    }

    public function revenueChart(): array
    {
        if ($this->period === 'daily') {
            $rows = Payment::where('status', 'success')
                ->where('created_at', '>=', now()->subDays(29))
                ->select(
                    DB::raw('DATE(created_at) as label'),
                    DB::raw('SUM(amount) as total')
                )
                ->groupBy('label')
                ->orderBy('label')
                ->pluck('total', 'label')
                ->toArray();

            $labels = [];
            $values = [];
            for ($i = 29; $i >= 0; $i--) {
                $date = now()->subDays($i)->toDateString();
                $labels[] = now()->subDays($i)->format('d M');
                $values[] = (float) ($rows[$date] ?? 0);
            }
        } else {
            $rows = Payment::where('status', 'success')
                ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
                ->select(
                    DB::raw('DATE_FORMAT(created_at, \'%Y-%m\') as label'),
                    DB::raw('SUM(amount) as total')
                )
                ->groupBy('label')
                ->orderBy('label')
                ->pluck('total', 'label')
                ->toArray();

            $labels = [];
            $values = [];
            for ($i = 11; $i >= 0; $i--) {
                $key = now()->subMonths($i)->format('Y-m');
                $labels[] = now()->subMonths($i)->format('M Y');
                $values[] = (float) ($rows[$key] ?? 0);
            }
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function topPlans(): \Illuminate\Support\Collection
    {
        return Subscription::query()
            ->select('plan_id', DB::raw('COUNT(*) as count'))
            ->groupBy('plan_id')
            ->orderByDesc('count')
            ->limit(5)
            ->with('plan')
            ->get();
    }
};
?>

<div>
    {{-- If you do not have a consistent goal in life, you can not live it in a consistent way. - Marcus Aurelius --}}
    <flux:heading size="xl" class="mb-6">Revenue Analytics</flux:heading>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">Today</flux:text>
            <div class="text-2xl font-bold text-purple-700 mt-1">TZS {{ $this->todayRevenue() }}</div>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">This Month</flux:text>
            <div class="text-2xl font-bold text-purple-700 mt-1">TZS {{ $this->monthRevenue() }}</div>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">All Time</flux:text>
            <div class="text-2xl font-bold text-purple-700 mt-1">TZS {{ $this->totalRevenue() }}</div>
        </flux:card>
        <flux:card class="text-center">
            <flux:text class="text-sm text-zinc-500">Active Now</flux:text>
            <div class="text-2xl font-bold text-green-600 mt-1">{{ $this->activeSubscriptions() }}</div>
        </flux:card>
    </div>

    {{-- Period Toggle --}}
    <div class="flex items-center gap-3 mb-4">
        <flux:heading size="lg">Revenue Trend</flux:heading>
        <flux:button.group>
            <flux:button size="sm" :variant="$period === 'daily' ? 'primary' : 'ghost'" wire:click="$set('period', 'daily')">Daily</flux:button>
            <flux:button size="sm" :variant="$period === 'monthly' ? 'primary' : 'ghost'" wire:click="$set('period', 'monthly')">Monthly</flux:button>
        </flux:button.group>
    </div>

    {{-- Simple bar chart using CSS/Tailwind --}}
    @php $chart = $this->revenueChart(); $max = max($chart['values'] ?: [1]); @endphp
    <flux:card class="mb-8 overflow-x-auto">
        <div class="flex items-end gap-1 h-40 min-w-0">
            @foreach ($chart['values'] as $i => $value)
                @php $height = $max > 0 ? ($value / $max) * 100 : 0; @endphp
                <div class="flex flex-col items-center flex-1 min-w-0 group" title="{{ $chart['labels'][$i] }}: TZS {{ number_format($value, 0) }}">
                    <div class="w-full rounded-t-sm bg-purple-600 hover:bg-purple-500 transition-all"
                         style="height: {{ $height }}%"></div>
                    @if (count($chart['labels']) <= 12)
                        <div class="text-[9px] text-zinc-400 mt-1 truncate w-full text-center">{{ $chart['labels'][$i] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </flux:card>

    {{-- Top Plans --}}
    <flux:heading size="lg" class="mb-3">Top Plans by Subscriptions</flux:heading>
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Plan</flux:table.column>
            <flux:table.column>Price</flux:table.column>
            <flux:table.column>Subscriptions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->topPlans() as $row)
                <flux:table.row :key="$row->plan_id">
                    <flux:table.cell class="font-semibold">{{ $row->plan?->name ?? 'Deleted' }}</flux:table.cell>
                    <flux:table.cell>TZS {{ $row->plan ? number_format($row->plan->price, 0) : '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="purple" size="sm">{{ $row->count }}</flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>