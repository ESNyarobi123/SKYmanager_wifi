<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/portal')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::view('routers', 'admin.routers')->name('routers');
        Route::view('plans', 'admin.plans')->name('plans');
        Route::view('sessions', 'admin.sessions')->name('sessions');
        Route::view('analytics', 'admin.analytics')->name('analytics');
    });
});

Route::view('/portal', 'portal.index')->name('portal');

require __DIR__.'/settings.php';
