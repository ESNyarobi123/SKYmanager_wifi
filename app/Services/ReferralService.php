<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;

class ReferralService
{
    /**
     * Reward days granted to both parties on a referral.
     */
    public const REWARD_DAYS = 1;

    /**
     * Generate a unique referral code for a customer.
     */
    public function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Apply a referral code during customer registration.
     * Creates the Referral record; reward is applied on first payment.
     */
    public function applyCode(User $newCustomer, string $code): ?Referral
    {
        $referrer = User::where('referral_code', $code)
            ->whereNot('id', $newCustomer->id)
            ->first();

        if (! $referrer) {
            return null;
        }

        $newCustomer->update(['referred_by' => $referrer->id]);

        return Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $newCustomer->id,
            'reward_days' => self::REWARD_DAYS,
            'reward_amount' => 0,
            'status' => 'pending',
        ]);
    }

    /**
     * Apply pending referral rewards for a customer after their first subscription.
     * Extends the latest active subscription of both parties by REWARD_DAYS.
     */
    public function applyPendingRewards(User $referredCustomer): void
    {
        $referral = Referral::where('referred_id', $referredCustomer->id)
            ->where('status', 'pending')
            ->first();

        if (! $referral) {
            return;
        }

        $rewardMinutes = self::REWARD_DAYS * 24 * 60;

        foreach ([$referral->referrer, $referredCustomer] as $customer) {
            if (! $customer) {
                continue;
            }

            $sub = Subscription::whereIn('router_id', $customer->routers()->pluck('id'))
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->orderBy('expires_at', 'desc')
                ->first();

            if ($sub) {
                $sub->update(['expires_at' => $sub->expires_at->addMinutes($rewardMinutes)]);
            }
        }

        $referral->update([
            'status' => 'applied',
            'applied_at' => now(),
        ]);
    }
}
