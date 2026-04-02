<?php

namespace App\Policies;

use App\Models\CustomerBillingPlan;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerBillingPlanPolicy
{
    use HandlesAuthorization;

    /**
     * Admins and super-admins can do everything.
     */
    public function before(User $actor, string $ability): ?bool
    {
        if ($actor->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('customer');
    }

    public function view(User $user, CustomerBillingPlan $plan): bool
    {
        return $plan->customer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('customer');
    }

    public function update(User $user, CustomerBillingPlan $plan): bool
    {
        return $plan->customer_id === $user->id;
    }

    public function delete(User $user, CustomerBillingPlan $plan): bool
    {
        return $plan->customer_id === $user->id;
    }

    public function restore(User $user, CustomerBillingPlan $plan): bool
    {
        return false;
    }

    public function forceDelete(User $user, CustomerBillingPlan $plan): bool
    {
        return false;
    }
}
