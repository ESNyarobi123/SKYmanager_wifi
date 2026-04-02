<?php

namespace App\Livewire\Customer;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.customer')]
class Notifications extends Component
{
    #[Computed]
    public function customer(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function notifications()
    {
        return $this->customer->notifications()->latest()->take(50)->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return $this->customer->unreadNotifications()->count();
    }

    public function markAllRead(): void
    {
        $this->customer->unreadNotifications()->update(['read_at' => now()]);
        unset($this->notifications, $this->unreadCount);
    }

    public function markRead(string $notificationId): void
    {
        $this->customer->notifications()->where('id', $notificationId)->update(['read_at' => now()]);
        unset($this->notifications, $this->unreadCount);
    }

    public function render()
    {
        return view('livewire.customer.notifications');
    }
}
