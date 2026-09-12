<?php

use Illuminate\Support\Facades\Route;
use Savv\Http\Controllers\AccountController;
use Savv\Http\Controllers\Auth\AuthenticatedSessionController;
use Savv\Http\Controllers\Auth\RegisteredUserController;
use Savv\Http\Controllers\ConnectionsController;
use Savv\Http\Controllers\DashboardController;
use Savv\Http\Controllers\ImportSessionController;
use Savv\Http\Controllers\OrderController;
use Savv\Http\Controllers\PrivacyController;
use Savv\Http\Controllers\RefundsController;
use Savv\Http\Controllers\ReturnsController;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/connections', [ConnectionsController::class, 'index'])->name('connections.index');
    Route::post('/connections/{provider}/start', [ConnectionsController::class, 'start'])
        ->whereIn('provider', ['amazon_in', 'flipkart'])
        ->middleware('throttle:import-session-start')
        ->name('connections.start');

    Route::prefix('imports/{importSession}')->name('imports.')->group(function () {
        Route::get('/', [ImportSessionController::class, 'show'])->name('show');
        Route::get('/browser', [ImportSessionController::class, 'browser'])->name('browser');
        Route::post('/scan', [ImportSessionController::class, 'scan'])
            ->middleware('throttle:import-session-action')
            ->name('scan');
        Route::get('/preview', [ImportSessionController::class, 'preview'])->name('preview');
        Route::post('/confirm', [ImportSessionController::class, 'confirm'])
            ->middleware('throttle:import-session-action')
            ->name('confirm');
        Route::post('/cancel', [ImportSessionController::class, 'cancel'])
            ->middleware('throttle:import-session-action')
            ->name('cancel');
    });

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');

    Route::get('/returns', [ReturnsController::class, 'index'])->name('returns.index');
    Route::get('/refunds', [RefundsController::class, 'index'])->name('refunds.index');

    Route::get('/settings/privacy', [PrivacyController::class, 'index'])->name('privacy.index');
    Route::get('/account/export', [AccountController::class, 'export'])->name('account.export');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');
});
