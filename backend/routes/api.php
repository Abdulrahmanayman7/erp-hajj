<?php

use App\Core\Auth\Controllers\AuthController;
use App\Core\Auth\Middleware\EnsureUserIsActive;
use App\Core\Health\HealthController;
use App\Modules\Authorization\Controllers\PermissionController;
use App\Modules\Authorization\Controllers\RoleController;
use App\Modules\Contracts\Controllers\ContractCategoryController;
use App\Modules\Contracts\Controllers\ContractController;
use App\Modules\Employees\Controllers\EmployeeController;
use App\Modules\Employees\Controllers\PositionController;
use App\Modules\Meetings\Controllers\MeetingAgendaItemController;
use App\Modules\Meetings\Controllers\MeetingAttendeeController;
use App\Modules\Meetings\Controllers\MeetingController;
use App\Modules\Meetings\Controllers\MeetingRecommendationController;
use App\Modules\OrganizationStructure\Controllers\OrganizationUnitController;
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

        Route::get('/organization-units', [OrganizationUnitController::class, 'index'])->name('organization-units.index');
        Route::post('/organization-units', [OrganizationUnitController::class, 'store'])->name('organization-units.store');
        Route::get('/organization-units/{organization_unit}', [OrganizationUnitController::class, 'show'])->name('organization-units.show');
        Route::patch('/organization-units/{organization_unit}', [OrganizationUnitController::class, 'update'])->name('organization-units.update');
        Route::post('/organization-units/{organization_unit}/move', [OrganizationUnitController::class, 'move'])->name('organization-units.move');
        Route::post('/organization-units/{organization_unit}/activate', [OrganizationUnitController::class, 'activate'])->name('organization-units.activate');
        Route::post('/organization-units/{organization_unit}/deactivate', [OrganizationUnitController::class, 'deactivate'])->name('organization-units.deactivate');
        Route::delete('/organization-units/{organization_unit}', [OrganizationUnitController::class, 'destroy'])->name('organization-units.destroy');

        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        Route::patch('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::post('/employees/{employee}/activate', [EmployeeController::class, 'activate'])->name('employees.activate');
        Route::post('/employees/{employee}/deactivate', [EmployeeController::class, 'deactivate'])->name('employees.deactivate');
        Route::put('/employees/{employee}/supervisor', [EmployeeController::class, 'assignSupervisor'])->name('employees.supervisor');
        Route::put('/employees/{employee}/user', [EmployeeController::class, 'linkUser'])->name('employees.user');

        Route::get('/positions', [PositionController::class, 'index'])->name('positions.index');
        Route::post('/positions', [PositionController::class, 'store'])->name('positions.store');
        Route::get('/positions/{position}', [PositionController::class, 'show'])->name('positions.show');
        Route::patch('/positions/{position}', [PositionController::class, 'update'])->name('positions.update');
        Route::post('/positions/{position}/activate', [PositionController::class, 'activate'])->name('positions.activate');
        Route::post('/positions/{position}/deactivate', [PositionController::class, 'deactivate'])->name('positions.deactivate');
        Route::delete('/positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');

        Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
        Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
        Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
        Route::patch('/contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update');
        Route::delete('/contracts/{contract}', [ContractController::class, 'destroy'])->name('contracts.destroy');
        Route::post('/contracts/{contract}/submit-review', [ContractController::class, 'submitReview'])->name('contracts.submit-review');
        Route::post('/contracts/{contract}/return-draft', [ContractController::class, 'returnDraft'])->name('contracts.return-draft');
        Route::post('/contracts/{contract}/approve', [ContractController::class, 'approve'])->name('contracts.approve');
        Route::post('/contracts/{contract}/sign', [ContractController::class, 'sign'])->name('contracts.sign');
        Route::post('/contracts/{contract}/execute', [ContractController::class, 'execute'])->name('contracts.execute');
        Route::post('/contracts/{contract}/close', [ContractController::class, 'close'])->name('contracts.close');
        Route::post('/contracts/{contract}/cancel', [ContractController::class, 'cancel'])->name('contracts.cancel');
        Route::post('/contracts/{contract}/renew', [ContractController::class, 'renew'])->name('contracts.renew');

        Route::get('/contract-categories', [ContractCategoryController::class, 'index'])->name('contract-categories.index');
        Route::post('/contract-categories', [ContractCategoryController::class, 'store'])->name('contract-categories.store');
        Route::get('/contract-categories/{contract_category}', [ContractCategoryController::class, 'show'])->name('contract-categories.show');
        Route::patch('/contract-categories/{contract_category}', [ContractCategoryController::class, 'update'])->name('contract-categories.update');
        Route::post('/contract-categories/{contract_category}/activate', [ContractCategoryController::class, 'activate'])->name('contract-categories.activate');
        Route::post('/contract-categories/{contract_category}/deactivate', [ContractCategoryController::class, 'deactivate'])->name('contract-categories.deactivate');
        Route::delete('/contract-categories/{contract_category}', [ContractCategoryController::class, 'destroy'])->name('contract-categories.destroy');

        Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings.index');
        Route::post('/meetings', [MeetingController::class, 'store'])->name('meetings.store');
        Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
        Route::patch('/meetings/{meeting}', [MeetingController::class, 'update'])->name('meetings.update');
        Route::delete('/meetings/{meeting}', [MeetingController::class, 'destroy'])->name('meetings.destroy');
        Route::post('/meetings/{meeting}/schedule', [MeetingController::class, 'schedule'])->name('meetings.schedule');
        Route::post('/meetings/{meeting}/reschedule', [MeetingController::class, 'reschedule'])->name('meetings.reschedule');
        Route::post('/meetings/{meeting}/start', [MeetingController::class, 'start'])->name('meetings.start');
        Route::post('/meetings/{meeting}/complete', [MeetingController::class, 'complete'])->name('meetings.complete');
        Route::post('/meetings/{meeting}/cancel', [MeetingController::class, 'cancel'])->name('meetings.cancel');
        Route::put('/meetings/{meeting}/minutes', [MeetingController::class, 'updateMinutes'])->name('meetings.minutes');

        Route::scopeBindings()->group(function (): void {
            Route::get('/meetings/{meeting}/attendees', [MeetingAttendeeController::class, 'index'])->name('meetings.attendees.index');
            Route::post('/meetings/{meeting}/attendees', [MeetingAttendeeController::class, 'store'])->name('meetings.attendees.store');
            Route::patch('/meetings/{meeting}/attendees/{attendee}', [MeetingAttendeeController::class, 'update'])->name('meetings.attendees.update');
            Route::delete('/meetings/{meeting}/attendees/{attendee}', [MeetingAttendeeController::class, 'destroy'])->name('meetings.attendees.destroy');

            Route::get('/meetings/{meeting}/agenda-items', [MeetingAgendaItemController::class, 'index'])->name('meetings.agenda-items.index');
            Route::post('/meetings/{meeting}/agenda-items', [MeetingAgendaItemController::class, 'store'])->name('meetings.agenda-items.store');
            Route::patch('/meetings/{meeting}/agenda-items/{agenda_item}', [MeetingAgendaItemController::class, 'update'])->name('meetings.agenda-items.update');
            Route::delete('/meetings/{meeting}/agenda-items/{agenda_item}', [MeetingAgendaItemController::class, 'destroy'])->name('meetings.agenda-items.destroy');

            Route::get('/meetings/{meeting}/recommendations', [MeetingRecommendationController::class, 'index'])->name('meetings.recommendations.index');
            Route::post('/meetings/{meeting}/recommendations', [MeetingRecommendationController::class, 'store'])->name('meetings.recommendations.store');
            Route::patch('/meetings/{meeting}/recommendations/{recommendation}', [MeetingRecommendationController::class, 'update'])->name('meetings.recommendations.update');
            Route::delete('/meetings/{meeting}/recommendations/{recommendation}', [MeetingRecommendationController::class, 'destroy'])->name('meetings.recommendations.destroy');
        });
    });
});
