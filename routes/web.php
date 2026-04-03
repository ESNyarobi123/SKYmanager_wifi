<?php

use App\Http\Controllers\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Customer\InvoiceController as CustomerInvoiceController;
use App\Http\Controllers\Customer\PortalHtmlController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\HotspotController;
use App\Http\Controllers\SpeedTestController;
use App\Livewire\WelcomePage;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomePage::class)->name('home');

// ── Laravel Boost dev-tool: only accepts POST; redirect GET gracefully ────────
Route::get('/_boost/browser-logs', fn () => redirect('/'))->name('boost.browser-logs.get');

// ── Admin routes (existing, untouched) ───────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::view('routers', 'admin.routers')->name('routers');
        Route::view('plans', 'admin.plans')->name('plans');
        Route::view('sessions', 'admin.sessions')->name('sessions');
        Route::view('analytics', 'admin.analytics')->name('analytics');
        Route::view('vouchers', 'admin.vouchers')->name('vouchers');
        Route::view('monitoring', 'admin.monitoring')->name('monitoring');
        Route::view('tools', 'admin.tools')->name('tools');
        Route::view('radius', 'admin.radius')->name('radius');
        Route::view('hotspot', 'admin.hotspot')->name('hotspot');
        Route::view('customers', 'admin.customers')->name('customers');
        Route::view('portal-customers', 'admin.portal-customers')->name('portal-customers');
        Route::view('system-settings', 'admin.system-settings')->name('system-settings');
        Route::view('activity-log', 'admin.activity-log')->name('activity-log');
    });
});

// ── Customer auth routes (guest only) ─────────────────────────────────────────
Route::prefix('customer')->name('customer.')->middleware('guest')->group(function () {
    Route::get('login', [CustomerAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [CustomerAuthController::class, 'login'])->name('login.store');
    Route::get('register', [CustomerAuthController::class, 'showRegister'])->name('register');
    Route::post('register', [CustomerAuthController::class, 'register'])->name('register.store');
});

// ── Customer authenticated routes (unified web guard + role:customer) ──────────
Route::prefix('customer')->name('customer.')->middleware(['auth', 'role:customer'])->group(function () {
    Route::post('logout', [CustomerAuthController::class, 'logout'])->name('logout');

    Route::view('dashboard', 'customer.dashboard')->name('dashboard');
    Route::view('routers', 'customer.routers')->name('routers');
    Route::view('routers/claim', 'customer.claim-router')->name('routers.claim');
    Route::view('subscriptions', 'customer.subscriptions')->name('subscriptions');
    Route::view('invoices', 'customer.invoices')->name('invoices');
    Route::get('invoices/{invoice}/download', [CustomerInvoiceController::class, 'download'])->name('invoices.download');
    Route::view('notifications', 'customer.notifications')->name('notifications');
    Route::view('referral', 'customer.referral')->name('referral');
    Route::view('payment-settings', 'customer.payment-settings')->name('payment-settings');
    Route::view('plans', 'customer.plans')->name('plans');
    Route::get('plans/download-login-html/{routerId}', [PortalHtmlController::class, 'download'])
        ->name('plans.download-login-html');
    Route::get('plans/preview-login-html/{routerId}', [PortalHtmlController::class, 'preview'])
        ->name('plans.preview-login-html');
});

// ── Public / portal routes ─────────────────────────────────────────────────────
Route::view('/portal', 'portal.index')->name('portal');
Route::get('/p/{subdomain}', [CustomerPortalController::class, 'show'])->name('portal.customer');

Route::get('/speedtest/ping', [SpeedTestController::class, 'ping'])->name('speedtest.ping');
Route::get('/speedtest/download', [SpeedTestController::class, 'download'])->name('speedtest.download');
Route::post('/speedtest/upload', [SpeedTestController::class, 'upload'])->name('speedtest.upload');

Route::get('/hotspot-login.html', [HotspotController::class, 'loginHtml'])->name('hotspot.login-html');

require __DIR__.'/settings.php';
