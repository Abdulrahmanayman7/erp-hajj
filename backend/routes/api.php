<?php

use App\Core\Auth\Controllers\AuthController;
use App\Core\Auth\Middleware\EnsureUserIsActive;
use App\Core\Health\HealthController;
use App\Modules\Authorization\Controllers\PermissionController;
use App\Modules\Authorization\Controllers\RoleController;
use App\Modules\Users\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', HealthController::class)->name('health');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

            Route::middleware(EnsureUserIsActive::class)->group(function (): void {
                Route::get('/me', [AuthController::class, 'me'])->name('me');
            });
        });
    });

    Route::middleware(['auth:sanctum', 'user.active', 'tenant.active'])->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
        Route::post('/users/{user}/enable', [UserController::class, 'enable'])->name('users.enable');
        Route::put('/users/{user}/roles', [UserController::class, 'syncRoles'])->name('users.roles');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::post('/roles/{role}/deactivate', [RoleController::class, 'deactivate'])->name('roles.deactivate');
        Route::post('/roles/{role}/activate', [RoleController::class, 'activate'])->name('roles.activate');
        Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.permissions');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    });
});
