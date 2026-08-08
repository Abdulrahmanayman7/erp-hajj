<?php

use App\Core\Auth\Controllers\AuthController;
use App\Core\Auth\Middleware\EnsureUserIsActive;
use App\Core\Health\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', HealthController::class)->name('health');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');

        Route::middleware('auth:sanctum')->group(function (): void {
            // Disabled accounts may still logout to clear a stale session.
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

            Route::middleware(EnsureUserIsActive::class)->group(function (): void {
                Route::get('/me', [AuthController::class, 'me'])->name('me');
            });
        });
    });
});
