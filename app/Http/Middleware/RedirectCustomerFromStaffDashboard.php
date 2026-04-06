<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customers should use the customer portal home; /dashboard is the staff KPI view.
 *
 * Admin URLs under /admin are denied with 403 so permission middleware (e.g. `can:`) can
 * express expected semantics in tests and for API-like clients — not a 302 redirect.
 */
class RedirectCustomerFromStaffDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $isCustomerOnly = $user->hasRole('customer')
            && ! $user->hasAnyRole(['admin', 'super-admin', 'reseller']);

        if (! $isCustomerOnly) {
            return $next($request);
        }

        if ($request->routeIs('dashboard')) {
            return redirect()->route('customer.dashboard');
        }

        if ($request->is('admin') || $request->is('admin/*')) {
            abort(403);
        }

        return $next($request);
    }
}
