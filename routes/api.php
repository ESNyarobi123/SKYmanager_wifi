<?php

use App\Http\Controllers\Api\Customer\AuthController;
use App\Http\Controllers\Api\Customer\InvoiceController;
use App\Http\Controllers\Api\Customer\RouterController;
use App\Http\Controllers\Api\Customer\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SKYmanager Mobile API
|--------------------------------------------------------------------------
| Sanctum token-auth routes for the mobile app.
| POST /api/customer/login  → returns bearer token
| All other routes require: Authorization: Bearer {token}
*/

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
