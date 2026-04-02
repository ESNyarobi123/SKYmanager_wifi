<?php

namespace App\Livewire\Customer;

use App\Models\Setting;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.customer')]
class Referral extends Component
{
    #[Computed]
    public function customer(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function referralLink(): string
    {
        $code = $this->customer->referral_code;

        if (! $code) {
            return '';
        }

        return route('customer.register', ['ref' => $code]);
    }

    #[Computed]
    public function referrals()
    {
        return $this->customer->referrals()
            ->with('referred')
            ->latest()
            ->get();
    }

    #[Computed]
    public function rewardDays(): int
    {
        return (int) Setting::get('referral_reward_days', ReferralService::REWARD_DAYS);
    }

    #[Computed]
    public function appliedCount(): int
    {
        return $this->customer->referrals()->where('status', 'applied')->count();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return $this->customer->referrals()->where('status', 'pending')->count();
    }

    public function render()
    {
        return view('livewire.customer.referral');
    }
}
