<?php

use App\Http\Controllers\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Customer\ClientSessionExportController;
use App\Http\Controllers\Customer\InvoiceController as CustomerInvoiceController;
use App\Http\Controllers\Customer\PortalHtmlController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\HotspotBundleController;
use App\Http\Controllers\HotspotController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SpeedTestController;
use App\Http\Middleware\RedirectCustomerFromStaffDashboard;
use App\Livewire\WelcomePage;
use App\Models\Router;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomePage::class)->name('home');

// ── Laravel Boost dev-tool: only accepts POST; redirect GET gracefully ────────
Route::get('/_boost/browser-logs', fn () => redirect('/'))->name('boost.browser-logs.get');

// ── Admin routes (existing, untouched) ───────────────────────────────────────
Route::middleware(['auth', 'verified', RedirectCustomerFromStaffDashboard::class])->group(function () {
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

        Route::view('router-operations', 'admin.router-operations')
            ->middleware('can:router-operations.view')
            ->name('router-operations');
        Route::get('router-operations/{router}', function (Router $router) {
            return view('admin.router-operations-detail', ['router' => $router]);
        })->middleware('can:router-operations.view')
            ->name('router-operations.show');
        Route::view('hotspot-payment-support', 'admin.hotspot-payment-support')
            ->middleware('can:hotspot-payments.support')
            ->name('hotspot-payment-support');
        Route::view('reports', 'admin.reports')
            ->middleware('can:reports.view')
            ->name('reports');
        Route::view('support-exports', 'admin.support-exports')
            ->middleware('can:reports.export')
            ->name('support-exports');
        Route::get('exports/{type}', [ReportExportController::class, 'download'])
            ->middleware('can:reports.export')
            ->where('type', 'revenue|hotspot_payments|router_operations|plan_performance|support_incidents|invoices|vouchers')
            ->name('exports.download');
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
    Route::get('invoices/export', [CustomerInvoiceController::class, 'exportCsv'])->name('invoices.export');
    Route::get('invoices/{invoice}/download', [CustomerInvoiceController::class, 'download'])->name('invoices.download');
    Route::view('notifications', 'customer.notifications')->name('notifications');
    Route::view('referral', 'customer.referral')->name('referral');
    Route::view('payment-settings', 'customer.payment-settings')->name('payment-settings');
    Route::view('plans', 'customer.plans')->name('plans');
    Route::view('client-sessions', 'customer.client-sessions')->name('client-sessions');
    Route::get('client-sessions/export', [ClientSessionExportController::class, 'csv'])->name('client-sessions.export');
    Route::get('plans/download-login-html/{routerId}', [PortalHtmlController::class, 'download'])
        ->name('plans.download-login-html');
    Route::get('plans/preview-login-html/{routerId}', [PortalHtmlController::class, 'preview'])
        ->name('plans.preview-login-html');
    Route::get('plans/hotspot-bundle/{routerId}', [PortalHtmlController::class, 'bundleOverview'])
        ->name('plans.hotspot-bundle');
    Route::get('plans/hotspot-bundle-file/{routerId}/{file}', [PortalHtmlController::class, 'bundleFile'])
        ->where('file', '[a-zA-Z0-9._-]+')
        ->name('plans.hotspot-bundle-file');
});

// ── Public / portal routes ─────────────────────────────────────────────────────
Route::view('/portal', 'portal.index')->name('portal');
Route::get('/p/{subdomain}', [CustomerPortalController::class, 'show'])->name('portal.customer');

Route::get('/speedtest/ping', [SpeedTestController::class, 'ping'])->name('speedtest.ping');
Route::get('/speedtest/download', [SpeedTestController::class, 'download'])->name('speedtest.download');
Route::post('/speedtest/upload', [SpeedTestController::class, 'upload'])->name('speedtest.upload');

Route::get('/hotspot-login.html', [HotspotController::class, 'loginHtml'])->name('hotspot.login-html');

Route::prefix('hotspot-bundle')->middleware('throttle:180,1')->group(function () {
    Route::get('{router}/manifest.json', [HotspotBundleController::class, 'manifest'])
        ->name('hotspot-bundle.manifest');
    Route::get('{router}/install.rsc', [HotspotBundleController::class, 'installRsc'])
        ->name('hotspot-bundle.install-rsc');
    Route::get('{router}/{file}', [HotspotBundleController::class, 'file'])
        ->where('file', '[a-zA-Z0-9._-]+')
        ->name('hotspot-bundle.file');
});

require __DIR__.'/settings.php';
