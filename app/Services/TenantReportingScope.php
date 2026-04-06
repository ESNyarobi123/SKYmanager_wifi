<?php

namespace App\Services;

use App\Models\HotspotPayment;
use App\Models\Router;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scopes reporting, KPIs, and exports to a router owner (customer/reseller) or full platform (admin).
 */
final class TenantReportingScope
{
    public static function isPlatformAdmin(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']);
    }

    /**
     * Router IDs visible to this actor. Empty array means no routers; null means all (platform admin).
     *
     * @return list<string>|null
     */
    public static function routerIdsFor(User $user): ?array
    {
        if (self::isPlatformAdmin($user)) {
            return null;
        }

        return $user->routers()->pluck('id')->all();
    }

    /**
     * @param  Builder<Router>  $query
     * @return Builder<Router>
     */
    public static function constrainRouters(Builder $query, ?array $routerIds): Builder
    {
        if ($routerIds === null) {
            return $query;
        }

        if ($routerIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $routerIds);
    }

    /**
     * @param  Builder<HotspotPayment>  $query
     * @return Builder<HotspotPayment>
     */
    public static function constrainHotspotPayments(Builder $query, ?array $routerIds): Builder
    {
        if ($routerIds === null) {
            return $query;
        }

        if ($routerIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('router_id', $routerIds);
    }
}
