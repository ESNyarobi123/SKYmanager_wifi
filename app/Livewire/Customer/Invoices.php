<?php

namespace App\Livewire\Customer;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.customer')]
class Invoices extends Component
{
    public string $statusFilter = 'all';

    #[Computed]
    public function customer(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function invoices()
    {
        $query = $this->customer->invoices()
            ->with(['subscription.plan', 'subscription.router', 'payment'])
            ->latest();

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return $query->get();
    }

    #[Computed]
    public function totalPaid(): float
    {
        return (float) $this->customer->invoices()->where('status', 'paid')->sum('total');
    }

    #[Computed]
    public function invoiceCount(): int
    {
        return $this->customer->invoices()->count();
    }

    public function updatedStatusFilter(): void
    {
        unset($this->invoices);
    }

    public function render()
    {
        return view('livewire.customer.invoices');
    }
}
