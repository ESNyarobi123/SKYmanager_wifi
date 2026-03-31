<?php

use App\Models\BillingPlan;
use App\Models\Payment;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\WifiUser;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function stats(): array
    {
        return [
            'active_sessions' => Subscription::where('status', 'active')
                ->where('expires_at', '>', now())
                ->count(),
            'online_routers' => Router::where('is_online', true)->count(),
            'total_routers' => Router::count(),
            'revenue_today' => Payment::where('status', 'success')
                ->whereDate('created_at', today())
                ->sum('amount'),
            'revenue_month' => Payment::where('status', 'success')
                ->whereMonth('created_at', now()->month)
                ->sum('amount'),
            'active_plans' => BillingPlan::where('is_active', true)->count(),
            'total_users' => WifiUser::count(),
        ];
    }

    #[Computed]
    public function recentPayments(): \Illuminate\Database\Eloquent\Collection
    {
        return Payment::with(['subscription.wifiUser', 'subscription.plan'])
            ->latest()
            ->limit(8)
            ->get();
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Page Title --}}
    <div>
        <flux:heading size="xl">Dashboard</flux:heading>
        <flux:subheading>Welcome back. Here's what's happening on your network.</flux:subheading>
    </div>

    {{-- KPI Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

        {{-- Active Sessions --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Active Sessions</span>
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30">
                    <svg class="h-5 w-5 text-purple-700 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-bold text-neutral-900 dark:text-white">{{ $this->stats['active_sessions'] }}</div>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">Currently connected users</p>
        </div>

        {{-- Routers --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Routers Online</span>
                <div class="flex h-9 w-9 items-center justify-center rounded-lg {{ $this->stats['online_routers'] > 0 ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30' }}">
                    <svg class="h-5 w-5 {{ $this->stats['online_routers'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-bold text-neutral-900 dark:text-white">
                {{ $this->stats['online_routers'] }}<span class="text-lg font-normal text-neutral-400">/{{ $this->stats['total_routers'] }}</span>
            </div>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">{{ $this->stats['total_routers'] === 0 ? 'No routers configured' : 'Routers operational' }}</p>
        </div>

        {{-- Revenue Today --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Revenue Today</span>
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                    <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-bold text-neutral-900 dark:text-white">{{ number_format($this->stats['revenue_today'], 0) }}</div>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">TZS &bull; Month: {{ number_format($this->stats['revenue_month'], 0) }}</p>
        </div>

        {{-- Active Plans --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Active Plans</span>
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                    <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
            </div>
            <div class="text-3xl font-bold text-neutral-900 dark:text-white">{{ $this->stats['active_plans'] }}</div>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">{{ $this->stats['total_users'] }} total registered users</p>
        </div>

    </div>

    {{-- Quick Nav + Recent Payments --}}
    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Quick Navigation --}}
        <div class="flex flex-col gap-3">
            <flux:heading size="lg">Quick Access</flux:heading>
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('admin.routers') }}" wire:navigate
                    class="group flex flex-col gap-2 rounded-xl border border-neutral-200 bg-white p-4 hover:border-purple-400 hover:shadow-md transition-all dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30 group-hover:bg-purple-200 transition-colors">
                        <svg class="h-4 w-4 text-purple-700 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Routers</span>
                </a>
                <a href="{{ route('admin.plans') }}" wire:navigate
                    class="group flex flex-col gap-2 rounded-xl border border-neutral-200 bg-white p-4 hover:border-purple-400 hover:shadow-md transition-all dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30 group-hover:bg-blue-200 transition-colors">
                        <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Plans</span>
                </a>
                <a href="{{ route('admin.sessions') }}" wire:navigate
                    class="group flex flex-col gap-2 rounded-xl border border-neutral-200 bg-white p-4 hover:border-purple-400 hover:shadow-md transition-all dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/30 group-hover:bg-orange-200 transition-colors">
                        <svg class="h-4 w-4 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Sessions</span>
                </a>
                <a href="{{ route('admin.analytics') }}" wire:navigate
                    class="group flex flex-col gap-2 rounded-xl border border-neutral-200 bg-white p-4 hover:border-purple-400 hover:shadow-md transition-all dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30 group-hover:bg-emerald-200 transition-colors">
                        <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Analytics</span>
                </a>
            </div>
        </div>

        {{-- Recent Payments --}}
        <div class="lg:col-span-2 flex flex-col gap-3">
            <flux:heading size="lg">Recent Payments</flux:heading>
            <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900 overflow-hidden">
                @if ($this->recentPayments->isEmpty())
                    <div class="py-12 text-center text-sm text-neutral-400">No payments recorded yet.</div>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-neutral-100 dark:border-neutral-800">
                                <th class="px-4 py-3 text-left text-xs font-medium text-neutral-400 uppercase tracking-wider">User / Phone</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-neutral-400 uppercase tracking-wider">Plan</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-neutral-400 uppercase tracking-wider">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-neutral-400 uppercase tracking-wider">Channel</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-neutral-400 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @foreach ($this->recentPayments as $payment)
                                <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <td class="px-4 py-3 text-neutral-700 dark:text-neutral-300 font-mono text-xs">
                                        {{ $payment->subscription?->wifiUser?->phone_number ?? $payment->subscription?->wifiUser?->mac_address ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400 text-xs">
                                        {{ $payment->subscription?->plan?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 font-medium text-neutral-900 dark:text-white text-xs">
                                        {{ number_format($payment->amount, 0) }} TZS
                                    </td>
                                    <td class="px-4 py-3 text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ $payment->provider ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($payment->status === 'success')
                                            <flux:badge color="green" size="sm">Paid</flux:badge>
                                        @elseif ($payment->status === 'pending')
                                            <flux:badge color="yellow" size="sm">Pending</flux:badge>
                                        @else
                                            <flux:badge color="red" size="sm">Failed</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

    </div>

</div>
