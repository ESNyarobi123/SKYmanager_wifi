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
        <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-neutral-500">Welcome back. Here's what's happening on your network.</p>
    </div>

    {{-- KPI Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

        {{-- Active Sessions --}}
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-purple-100 dark:bg-purple-800/30">
                    <x-lucide name="wifi" class="size-5 text-purple-700 dark:text-purple-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">Active Sessions</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 dark:text-neutral-200">{{ $this->stats['active_sessions'] }}</p>
            <p class="text-xs text-gray-500 dark:text-neutral-500 mt-1">Currently connected users</p>
        </div>

        {{-- Routers --}}
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg {{ $this->stats['online_routers'] > 0 ? 'bg-emerald-100 dark:bg-emerald-800/30' : 'bg-red-100 dark:bg-red-800/30' }}">
                    <x-lucide name="server" class="size-5 {{ $this->stats['online_routers'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">Routers Online</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 dark:text-neutral-200">
                {{ $this->stats['online_routers'] }}<span class="text-lg font-normal text-gray-400 dark:text-neutral-500">/{{ $this->stats['total_routers'] }}</span>
            </p>
            <p class="text-xs text-gray-500 dark:text-neutral-500 mt-1">{{ $this->stats['total_routers'] === 0 ? 'No routers configured' : 'Routers operational' }}</p>
        </div>

        {{-- Revenue Today --}}
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-teal-100 dark:bg-teal-800/30">
                    <x-lucide name="bar-chart-3" class="size-5 text-teal-600 dark:text-teal-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">Revenue Today</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 dark:text-neutral-200">{{ number_format($this->stats['revenue_today'], 0) }}</p>
            <p class="text-xs text-gray-500 dark:text-neutral-500 mt-1">TZS &bull; Month: {{ number_format($this->stats['revenue_month'], 0) }}</p>
        </div>

        {{-- Active Plans --}}
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-5">
            <div class="flex items-center gap-x-3 mb-3">
                <div class="inline-flex items-center justify-center size-10 rounded-lg bg-blue-100 dark:bg-blue-800/30">
                    <x-lucide name="layers" class="size-5 text-blue-600 dark:text-blue-400"/>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wide">Active Plans</span>
            </div>
            <p class="text-3xl font-bold text-gray-800 dark:text-neutral-200">{{ $this->stats['active_plans'] }}</p>
            <p class="text-xs text-gray-500 dark:text-neutral-500 mt-1">{{ $this->stats['total_users'] }} total registered users</p>
        </div>

    </div>

    {{-- Quick Nav + Recent Payments --}}
    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Quick Navigation --}}
        <div class="flex flex-col gap-3">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-neutral-300">Quick Access</h2>
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('admin.routers') }}" wire:navigate
                    class="group flex flex-col gap-3 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-4 hover:border-purple-400 hover:shadow-md transition-all">
                    <div class="inline-flex size-8 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-800/30 group-hover:bg-purple-200 dark:group-hover:bg-purple-800/50 transition-colors">
                        <x-lucide name="server" class="size-4 text-purple-700 dark:text-purple-400"/>
                    </div>
                    <span class="text-sm font-medium text-gray-700 dark:text-neutral-300">Routers</span>
                </a>
                <a href="{{ route('admin.plans') }}" wire:navigate
                    class="group flex flex-col gap-3 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-4 hover:border-purple-400 hover:shadow-md transition-all">
                    <div class="inline-flex size-8 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-800/30 group-hover:bg-blue-200 dark:group-hover:bg-blue-800/50 transition-colors">
                        <x-lucide name="layers" class="size-4 text-blue-600 dark:text-blue-400"/>
                    </div>
                    <span class="text-sm font-medium text-gray-700 dark:text-neutral-300">Plans</span>
                </a>
                <a href="{{ route('admin.sessions') }}" wire:navigate
                    class="group flex flex-col gap-3 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-4 hover:border-purple-400 hover:shadow-md transition-all">
                    <div class="inline-flex size-8 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-800/30 group-hover:bg-orange-200 dark:group-hover:bg-orange-800/50 transition-colors">
                        <x-lucide name="activity" class="size-4 text-orange-600 dark:text-orange-400"/>
                    </div>
                    <span class="text-sm font-medium text-gray-700 dark:text-neutral-300">Sessions</span>
                </a>
                <a href="{{ route('admin.analytics') }}" wire:navigate
                    class="group flex flex-col gap-3 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-4 hover:border-purple-400 hover:shadow-md transition-all">
                    <div class="inline-flex size-8 items-center justify-center rounded-lg bg-teal-100 dark:bg-teal-800/30 group-hover:bg-teal-200 dark:group-hover:bg-teal-800/50 transition-colors">
                        <x-lucide name="bar-chart-3" class="size-4 text-teal-600 dark:text-teal-400"/>
                    </div>
                    <span class="text-sm font-medium text-gray-700 dark:text-neutral-300">Analytics</span>
                </a>
            </div>
        </div>

        {{-- Recent Payments --}}
        <div class="lg:col-span-2 flex flex-col gap-3">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-neutral-300">Recent Payments</h2>
            <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 overflow-hidden">
                @if ($this->recentPayments->isEmpty())
                    <div class="py-12 text-center text-sm text-gray-400 dark:text-neutral-500">No payments recorded yet.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                        <thead class="bg-gray-50 dark:bg-neutral-700/30">
                            <tr>
                                <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">User / Phone</th>
                                <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">Plan</th>
                                <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">Amount</th>
                                <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">Channel</th>
                                <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                            @foreach ($this->recentPayments as $payment)
                                <tr class="hover:bg-gray-50 dark:hover:bg-neutral-700/20 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap font-mono text-xs text-gray-700 dark:text-neutral-300">
                                        {{ $payment->subscription?->wifiUser?->phone_number ?? $payment->subscription?->wifiUser?->mac_address ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600 dark:text-neutral-400">
                                        {{ $payment->subscription?->plan?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs font-semibold text-gray-800 dark:text-neutral-200">
                                        {{ number_format($payment->amount, 0) }} TZS
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-neutral-400">
                                        {{ $payment->provider ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
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
