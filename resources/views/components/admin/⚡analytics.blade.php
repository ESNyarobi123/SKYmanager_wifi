<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Voucher;
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

    public function voucherStats(): array
    {
        return [
            'total' => Voucher::count(),
            'unused' => Voucher::where('status', 'unused')->count(),
            'used' => Voucher::where('status', 'used')->count(),
            'expired' => Voucher::where('status', 'expired')->count(),
        ];
    }

    public function paymentsByProvider(): \Illuminate\Support\Collection
    {
        return Payment::where('status', 'success')
            ->select('provider', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('provider')
            ->orderByDesc('total')
            ->get();
    }

};
?>

<div>
    {{-- If you do not have a consistent goal in life, you can not live it in a consistent way. - Marcus Aurelius --}}
    <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200 mb-6">Revenue Analytics</h1>

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
        <h2 class="text-base font-semibold text-gray-700 dark:text-neutral-300">Revenue Trend</h2>
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

    <div class="grid lg:grid-cols-2 gap-8 mb-8">
        {{-- Top Plans --}}
        <div>
            <h2 class="text-sm font-semibold text-gray-700 dark:text-neutral-300 mb-3">Top Plans by Subscriptions</h2>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Plan</flux:table.column>
                    <flux:table.column>Price</flux:table.column>
                    <flux:table.column>Count</flux:table.column>
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

        {{-- Payments by Provider --}}
        <div>
            <h2 class="text-sm font-semibold text-gray-700 dark:text-neutral-300 mb-3">Revenue by Provider</h2>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Provider</flux:table.column>
                    <flux:table.column>Transactions</flux:table.column>
                    <flux:table.column>Revenue (TZS)</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->paymentsByProvider() as $row)
                        <flux:table.row :key="$row->provider">
                            <flux:table.cell class="font-semibold">{{ $row->provider }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="zinc" size="sm">{{ $row->count }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="font-semibold text-purple-700">{{ number_format($row->total, 0) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center text-zinc-400">No payments yet</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    {{-- Voucher Stats --}}
    @php $vs = $this->voucherStats(); @endphp
    <h2 class="text-sm font-semibold text-gray-700 dark:text-neutral-300 mb-3">Voucher Overview</h2>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="text-center">
            <div class="text-2xl font-bold text-zinc-700">{{ $vs['total'] }}</div>
            <flux:text class="text-sm text-zinc-500">Total Issued</flux:text>
        </flux:card>
        <flux:card class="text-center">
            <div class="text-2xl font-bold text-green-600">{{ $vs['unused'] }}</div>
            <flux:text class="text-sm text-zinc-500">Unused</flux:text>
        </flux:card>
        <flux:card class="text-center">
            <div class="text-2xl font-bold text-purple-700">{{ $vs['used'] }}</div>
            <flux:text class="text-sm text-zinc-500">Redeemed</flux:text>
        </flux:card>
        <flux:card class="text-center">
            <div class="text-2xl font-bold text-zinc-400">{{ $vs['expired'] }}</div>
            <flux:text class="text-sm text-zinc-500">Expired</flux:text>
        </flux:card>
    </div>
</div>