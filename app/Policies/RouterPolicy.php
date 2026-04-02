<?php

namespace App\Policies;

use App\Models\Router;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RouterPolicy
{
    use HandlesAuthorization;

    /**
     * Super-admins and admins can do everything with any router.
     * Customers and resellers can only act on their own routers.
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
        return true;
    }

    public function view(User $user, Router $router): bool
    {
        return $router->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Router $router): bool
    {
        return $router->user_id === $user->id;
    }

    public function delete(User $user, Router $router): bool
    {
        return $router->user_id === $user->id;
    }

    public function restore(User $user, Router $router): bool
    {
        return $router->user_id === $user->id;
    }

    public function forceDelete(User $user, Router $router): bool
    {
        return false;
    }
}
