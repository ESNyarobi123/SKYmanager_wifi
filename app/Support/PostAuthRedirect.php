<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolves where to send a user after Fortify (or similar) authentication.
 *
 * Prevents staff accounts from following a stale session "intended" URL under
 * /customer/* (which would 403 on role:customer middleware).
 */
final class PostAuthRedirect
{
    public static function url(Request $request): string
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return route('dashboard');
        }

        $default = self::defaultHome($user);
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || $intended === '') {
            return $default;
        }

        if (self::userMayAccessIntendedUrl($user, $intended)) {
            return $intended;
        }

        return $default;
    }

    public static function defaultHome(User $user): string
    {
        if ($user->hasRole('customer') && ! $user->hasAnyRole(['admin', 'super-admin', 'reseller'])) {
            return route('customer.dashboard');
        }

        return route('dashboard');
    }

    public static function userMayAccessIntendedUrl(User $user, string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return true;
        }

        $isCustomerArea = str_starts_with($path, '/customer');
        $isStaffHome = $path === '/dashboard';
        $isAdminArea = str_starts_with($path, '/admin');

        if ($isCustomerArea) {
            return $user->hasRole('customer');
        }

        if ($isStaffHome || $isAdminArea) {
            return $user->hasAnyRole(['admin', 'super-admin', 'reseller']);
        }

        return true;
    }
}
