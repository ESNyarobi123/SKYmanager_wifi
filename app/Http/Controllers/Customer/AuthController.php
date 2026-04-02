<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('customer.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($request->input('phone')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()->withErrors([
                'phone' => __('Too many login attempts. Please try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        $user = User::where('phone', $request->input('phone'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey);

            return back()->withErrors([
                'phone' => __('These credentials do not match our records.'),
            ])->withInput($request->only('phone'));
        }

        if ($user->isSuspended()) {
            RateLimiter::hit($throttleKey);

            return back()->withErrors([
                'phone' => __('Your account has been suspended. Please contact support.'),
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        ActivityLog::record('Customer logged in', $user, $user);

        return redirect()->intended(route('customer.dashboard'));
    }

    public function showRegister(): View
    {
        return view('customer.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $referralService = app(ReferralService::class);

        $user = User::create([
            'name' => $request->input('name'),
            'phone' => $request->input('phone'),
            'company_name' => $request->input('company_name'),
            'password' => Hash::make($request->input('password')),
            'referral_code' => $referralService->generateCode(),
            'phone_verified_at' => now(),
        ]);

        $user->assignRole(Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']));

        if ($request->filled('ref')) {
            $referralService->applyCode($user, $request->input('ref'));
        }

        ActivityLog::record('Customer registered', $user, $user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }
}
