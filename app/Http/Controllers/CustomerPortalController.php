<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CustomerPortalController extends Controller
{
    /**
     * Serve the dynamic captive portal for a specific customer subdomain.
     *
     * URL: /p/{subdomain}?mac={mac}&ip={ip}&link-orig={orig}
     * MikroTik passes these query params automatically via the login.html redirect.
     */
    public function show(string $subdomain): View|Response
    {
        $customer = User::where('portal_subdomain', $subdomain)->first();

        if (! $customer) {
            abort(404, 'Portal not found.');
        }

        $plans = $customer->billingPlans()
            ->where('is_active', true)
            ->orderBy('price')
            ->get();

        return view('portal.customer', compact('customer', 'plans'));
    }
}
