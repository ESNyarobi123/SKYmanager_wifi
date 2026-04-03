<?php

use App\Http\Controllers\Api\Customer\AuthController;
use App\Http\Controllers\Api\Customer\InvoiceController;
use App\Http\Controllers\Api\Customer\RouterController;
use App\Http\Controllers\Api\Customer\SubscriptionController;
use App\Http\Controllers\Api\LocalPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SKYmanager Mobile API
|--------------------------------------------------------------------------
| Sanctum token-auth routes for the mobile app.
| POST /api/customer/login  → returns bearer token
| All other routes require: Authorization: Bearer {token}
*/

// ══════════════════════════════════════════════════════════════════════════════
// Local Captive Portal — fully public, called from MikroTik-served login.html
// No authentication. Rate limiting is applied per-route below.
// The payment/callback route has NO throttle (must accept ClickPesa webhooks).
// ══════════════════════════════════════════════════════════════════════════════
Route::prefix('local-portal')->name('api.local-portal.')->group(function () {

    // ── Group 1: Portal Entry ─────────────────────────────────────────────
    Route::get('packages', [LocalPortalController::class, 'packages'])
        ->middleware('throttle:60,1')
        ->name('packages');

    Route::post('session/start', [LocalPortalController::class, 'startSession'])
        ->middleware('throttle:30,1')
        ->name('session.start');

    // ── Group 2: Payment ──────────────────────────────────────────────────
    Route::prefix('payment')->name('payment.')->group(function () {

        Route::post('initiate', [LocalPortalController::class, 'initiatePayment'])
            ->middleware('throttle:10,1')
            ->name('initiate');

        Route::get('status/{reference}', [LocalPortalController::class, 'paymentStatus'])
            ->middleware('throttle:60,1')
            ->name('status');

        // Completely public — ClickPesa sends webhooks here. Never throttle.
        Route::post('callback', [LocalPortalController::class, 'paymentCallback'])
            ->name('callback');
    });

    // ── Group 3: MikroTik Authorization ───────────────────────────────────
    Route::prefix('mikrotik')->name('mikrotik.')->group(function () {

        Route::post('authorize', [LocalPortalController::class, 'authorizeUser'])
            ->middleware('throttle:10,1')
            ->name('authorize');
    });

    // ── Group 4: Voucher Redemption ───────────────────────────────────────
    Route::prefix('voucher')->name('voucher.')->group(function () {

        Route::post('redeem', [LocalPortalController::class, 'redeemVoucher'])
            ->middleware('throttle:10,1')
            ->name('redeem');
    });
});

// ── Public (no auth required) ─────────────────────────────────────────────────
Route::prefix('customer')->name('api.customer.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('login');
});

// ── Authenticated customer API ────────────────────────────────────────────────
Route::prefix('customer')
    ->name('api.customer.')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');

        Route::get('routers', [RouterController::class, 'index'])->name('routers.index');
        Route::get('routers/{id}', [RouterController::class, 'show'])->name('routers.show');

        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    });
