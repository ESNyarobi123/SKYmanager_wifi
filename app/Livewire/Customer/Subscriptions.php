<?php

namespace App\Livewire\Customer;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.customer')]
class Subscriptions extends Component
{
    public string $statusFilter = 'all';

    #[Computed]
    public function customer(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function subscriptions()
    {
        $routerIds = $this->customer->routers()->pluck('id');

        $query = Subscription::whereIn('router_id', $routerIds)
            ->with(['plan', 'router', 'latestPayment']);

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return $query->latest()->get();
    }

    #[Computed]
    public function recentPayments()
    {
        $routerIds = $this->customer->routers()->pluck('id');
        $subscriptionIds = Subscription::whereIn('router_id', $routerIds)->pluck('id');

        return Payment::whereIn('subscription_id', $subscriptionIds)
            ->with(['subscription.plan', 'subscription.router'])
            ->latest()
            ->take(20)
            ->get();
    }

    #[Computed]
    public function totalSpend(): float
    {
        $routerIds = $this->customer->routers()->pluck('id');
        $subscriptionIds = Subscription::whereIn('router_id', $routerIds)->pluck('id');

        return (float) Payment::whereIn('subscription_id', $subscriptionIds)
            ->where('status', 'paid')
            ->sum('amount');
    }

    public function updatedStatusFilter(): void
    {
        unset($this->subscriptions);
    }

    public function render()
    {
        return view('livewire.customer.subscriptions');
    }
}
