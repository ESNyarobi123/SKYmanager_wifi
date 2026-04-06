<?php

namespace App\Livewire\Customer;

use App\Models\HotspotPayment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BusinessKpiService;
use App\Services\CustomerClientSessionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.customer')]
class Dashboard extends Component
{
    #[Computed]
    public function customer(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function routers()
    {
        return $this->customer->routers()
            ->with(['subscriptions' => fn ($q) => $q->where('status', 'active')->with('plan')])
            ->latest()
            ->get();
    }

    #[Computed]
    public function activeRouterCount(): int
    {
        return $this->customer->routers()->where('is_online', true)->count();
    }

    #[Computed]
    public function totalRouterCount(): int
    {
        return $this->customer->routers()->count();
    }

    #[Computed]
    public function activeSubscriptions()
    {
        return Subscription::query()
            ->whereIn('router_id', $this->customer->routers()->pluck('id'))
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->with(['plan', 'router'])
            ->orderBy('expires_at')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function expiringSoon()
    {
        return Subscription::query()
            ->whereIn('router_id', $this->customer->routers()->pluck('id'))
            ->where('status', 'active')
            ->whereBetween('expires_at', [now(), now()->addDays(3)])
            ->with(['plan', 'router'])
            ->orderBy('expires_at')
            ->get();
    }

    #[Computed]
    public function monthlySpend(): float
    {
        $routerIds = $this->customer->routers()->pluck('id');
        $subscriptionIds = Subscription::whereIn('router_id', $routerIds)->pluck('id');

        return (float) Payment::whereIn('subscription_id', $subscriptionIds)
            ->where('status', 'success')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
    }

    #[Computed]
    public function recentPayments()
    {
        $routerIds = $this->customer->routers()->pluck('id');
        $subscriptionIds = Subscription::whereIn('router_id', $routerIds)->pluck('id');

        return Payment::whereIn('subscription_id', $subscriptionIds)
            ->with(['subscription.plan', 'subscription.router'])
            ->latest()
            ->take(8)
            ->get();
    }

    #[Computed]
    public function unreadNotificationCount(): int
    {
        return $this->customer->unreadNotifications()->count();
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function operationsSummary(): array
    {
        return app(BusinessKpiService::class)->customerOperationsSummary($this->customer);
    }

    #[Computed]
    public function recentHotspotPayments()
    {
        $routerIds = $this->customer->routers()->pluck('id');
        if ($routerIds->isEmpty()) {
            return collect();
        }

        return HotspotPayment::query()
            ->whereIn('router_id', $routerIds)
            ->with(['router', 'plan'])
            ->latest()
            ->limit(8)
            ->get();
    }

    /**
     * @return array<string, int|string>
     */
    #[Computed]
    public function clientSessionKpis(): array
    {
        return app(CustomerClientSessionService::class)->dashboardKpis($this->customer);
    }

    public function render()
    {
        return view('livewire.customer.dashboard');
    }
}
