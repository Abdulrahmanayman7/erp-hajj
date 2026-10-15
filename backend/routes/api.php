<?php

use App\Core\Auth\Controllers\AuthController;
use App\Core\Auth\Middleware\EnsureUserIsActive;
use App\Core\Health\HealthController;
use App\Modules\Assets\Controllers\AssetCategoryController;
use App\Modules\Assets\Controllers\AssetController;
use App\Modules\Assets\Controllers\AssetCustodyController;
use App\Modules\Assets\Controllers\MyCustodyController;
use App\Modules\Audit\Controllers\AuditLogController;
use App\Modules\Authorization\Controllers\PermissionController;
use App\Modules\Authorization\Controllers\RoleController;
use App\Modules\Contracts\Controllers\ContractCategoryController;
use App\Modules\Contracts\Controllers\ContractController;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\Decisions\Controllers\DecisionController;
use App\Modules\Documents\Controllers\DocumentCategoryController;
use App\Modules\Documents\Controllers\DocumentController;
use App\Modules\Employees\Controllers\EmployeeController;
use App\Modules\Employees\Controllers\PositionController;
use App\Modules\Inventory\Controllers\InventoryBalanceController;
use App\Modules\Inventory\Controllers\InventoryCategoryController;
use App\Modules\Inventory\Controllers\InventoryItemController;
use App\Modules\Inventory\Controllers\InventoryMovementController;
use App\Modules\Inventory\Controllers\InventoryStockController;
use App\Modules\Inventory\Controllers\WarehouseController;
use App\Modules\Meetings\Controllers\MeetingAgendaItemController;
use App\Modules\Meetings\Controllers\MeetingAttendeeController;
use App\Modules\Meetings\Controllers\MeetingController;
use App\Modules\Meetings\Controllers\MeetingRecommendationController;
use App\Modules\Notifications\Controllers\NotificationController;
use App\Modules\OrganizationStructure\Controllers\OrganizationTreeController;
use App\Modules\OrganizationStructure\Controllers\OrganizationUnitController;
use App\Modules\Platform\Controllers\PlatformSetupController;
use App\Modules\Platform\Controllers\PlatformTenantController;
use App\Modules\SeasonPilgrims\Controllers\PilgrimController;
use App\Modules\SeasonPilgrims\Controllers\SeasonContextController;
use App\Modules\SeasonPilgrims\Controllers\SeasonController;
use App\Modules\SeasonPilgrims\Controllers\SeasonFamilyGroupController;
use App\Modules\SeasonPilgrims\Controllers\SeasonPilgrimController;
use App\Modules\SeasonPilgrims\Controllers\SeasonPilgrimGroupController;
use App\Modules\SeasonPilgrims\Controllers\SeasonProgramController;
use App\Modules\Settings\Controllers\TenantSettingsController;
use App\Modules\Tasks\Controllers\TaskController;
use App\Modules\Transportation\Controllers\TransportEntitlementController;
use App\Modules\Transportation\Controllers\TransportExternalDriverController;
use App\Modules\Transportation\Controllers\TransportMovementController;
use App\Modules\Transportation\Controllers\TransportPassengerController;
use App\Modules\Transportation\Controllers\TransportPointController;
use App\Modules\Transportation\Controllers\TransportReviewItemController;
use App\Modules\Transportation\Controllers\TransportRunController;
use App\Modules\Transportation\Controllers\TransportSeasonVehicleController;
use App\Modules\Transportation\Controllers\TransportTimelineController;
use App\Modules\Transportation\Controllers\TransportVehicleController;
use App\Modules\Users\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', HealthController::class)->name('health');

    Route::prefix('platform')->name('platform.')->group(function (): void {
        Route::get('/setup/status', [PlatformSetupController::class, 'status'])->name('setup.status');
        Route::post('/setup', [PlatformSetupController::class, 'store'])->name('setup.store');

        Route::middleware(['auth:sanctum', 'user.active', 'platform.user', 'platform.context'])->group(function (): void {
            Route::get('/tenants', [PlatformTenantController::class, 'index'])->name('tenants.index');
            Route::post('/tenants', [PlatformTenantController::class, 'store'])->name('tenants.store');
            Route::get('/tenants/{tenant}', [PlatformTenantController::class, 'show'])->name('tenants.show');
            Route::patch('/tenants/{tenant}', [PlatformTenantController::class, 'update'])->name('tenants.update');
            Route::post('/tenants/{tenant}/activate', [PlatformTenantController::class, 'activate'])->name('tenants.activate');
            Route::post('/tenants/{tenant}/suspend', [PlatformTenantController::class, 'suspend'])->name('tenants.suspend');
            Route::post('/tenants/{tenant}/archive', [PlatformTenantController::class, 'archive'])->name('tenants.archive');
            Route::post('/tenants/{tenant}/transfer-ownership', [PlatformTenantController::class, 'transferOwnership'])->name('tenants.transfer-ownership');
            Route::get('/tenants/{tenant}/users', [PlatformTenantController::class, 'users'])->name('tenants.users');
        });
    });

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
        Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard.show');

        Route::get('/tenant-settings', [TenantSettingsController::class, 'show'])->name('tenant-settings.show');
        Route::patch('/tenant-settings', [TenantSettingsController::class, 'update'])->name('tenant-settings.update');
        Route::post('/tenant-settings/test-email', [TenantSettingsController::class, 'testEmail'])->name('tenant-settings.test-email');

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

        Route::get('/organization-tree', [OrganizationTreeController::class, 'show'])->name('organization-tree.show');

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

            Route::get('/decisions', [DecisionController::class, 'index'])->name('decisions.index');
            Route::post('/decisions', [DecisionController::class, 'store'])->name('decisions.store');
            Route::get('/decisions/{decision}', [DecisionController::class, 'show'])->name('decisions.show');
            Route::patch('/decisions/{decision}', [DecisionController::class, 'update'])->name('decisions.update');
            Route::delete('/decisions/{decision}', [DecisionController::class, 'destroy'])->name('decisions.destroy');
            Route::post('/decisions/{decision}/submit', [DecisionController::class, 'submit'])->name('decisions.submit');
            Route::post('/decisions/{decision}/return-draft', [DecisionController::class, 'returnDraft'])->name('decisions.return-draft');
            Route::post('/decisions/{decision}/approve', [DecisionController::class, 'approve'])->name('decisions.approve');
            Route::post('/decisions/{decision}/cancel', [DecisionController::class, 'cancel'])->name('decisions.cancel');
            Route::post('/decisions/{decision}/close', [DecisionController::class, 'close'])->name('decisions.close');

            Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
            Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
            Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
            Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
            Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
            Route::put('/tasks/{task}/assignee', [TaskController::class, 'assign'])->name('tasks.assign');
            Route::post('/tasks/{task}/start', [TaskController::class, 'start'])->name('tasks.start');
            Route::put('/tasks/{task}/progress', [TaskController::class, 'progress'])->name('tasks.progress');
            Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
            Route::post('/tasks/{task}/cancel', [TaskController::class, 'cancel'])->name('tasks.cancel');

            Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
            Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
            Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
            Route::patch('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
            Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
            Route::post('/documents/{document}/archive', [DocumentController::class, 'archive'])->name('documents.archive');
            Route::post('/documents/{document}/restore', [DocumentController::class, 'restore'])->name('documents.restore');
            Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

            Route::get('/document-categories', [DocumentCategoryController::class, 'index'])->name('document-categories.index');
            Route::post('/document-categories', [DocumentCategoryController::class, 'store'])->name('document-categories.store');
            Route::patch('/document-categories/{document_category}', [DocumentCategoryController::class, 'update'])->name('document-categories.update');
            Route::delete('/document-categories/{document_category}', [DocumentCategoryController::class, 'destroy'])->name('document-categories.destroy');

            Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
            Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
            Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');
            Route::patch('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
            Route::post('/warehouses/{warehouse}/activate', [WarehouseController::class, 'activate'])->name('warehouses.activate');
            Route::post('/warehouses/{warehouse}/deactivate', [WarehouseController::class, 'deactivate'])->name('warehouses.deactivate');
            Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');

            Route::get('/inventory-categories', [InventoryCategoryController::class, 'index'])->name('inventory-categories.index');
            Route::post('/inventory-categories', [InventoryCategoryController::class, 'store'])->name('inventory-categories.store');
            Route::patch('/inventory-categories/{category}', [InventoryCategoryController::class, 'update'])->name('inventory-categories.update');
            Route::delete('/inventory-categories/{category}', [InventoryCategoryController::class, 'destroy'])->name('inventory-categories.destroy');

            Route::get('/inventory-items', [InventoryItemController::class, 'index'])->name('inventory-items.index');
            Route::post('/inventory-items', [InventoryItemController::class, 'store'])->name('inventory-items.store');
            Route::get('/inventory-items/{inventory_item}', [InventoryItemController::class, 'show'])->name('inventory-items.show');
            Route::patch('/inventory-items/{inventory_item}', [InventoryItemController::class, 'update'])->name('inventory-items.update');
            Route::post('/inventory-items/{inventory_item}/activate', [InventoryItemController::class, 'activate'])->name('inventory-items.activate');
            Route::post('/inventory-items/{inventory_item}/deactivate', [InventoryItemController::class, 'deactivate'])->name('inventory-items.deactivate');
            Route::delete('/inventory-items/{inventory_item}', [InventoryItemController::class, 'destroy'])->name('inventory-items.destroy');
            Route::get('/inventory-items/{inventory_item}/balances', [InventoryItemController::class, 'balances'])->name('inventory-items.balances');

            Route::get('/inventory/balances', [InventoryBalanceController::class, 'index'])->name('inventory.balances.index');
            Route::get('/inventory/movements', [InventoryMovementController::class, 'index'])->name('inventory.movements.index');
            Route::get('/inventory/movements/{movement}', [InventoryMovementController::class, 'show'])->name('inventory.movements.show');
            Route::post('/inventory/receipts', [InventoryStockController::class, 'receive'])->name('inventory.receipts.store');
            Route::post('/inventory/issues', [InventoryStockController::class, 'issue'])->name('inventory.issues.store');
            Route::post('/inventory/returns', [InventoryStockController::class, 'returnStock'])->name('inventory.returns.store');
            Route::post('/inventory/transfers', [InventoryStockController::class, 'transfer'])->name('inventory.transfers.store');
            Route::post('/inventory/adjustments', [InventoryStockController::class, 'adjust'])->name('inventory.adjustments.store');

            Route::get('/asset-categories', [AssetCategoryController::class, 'index'])->name('asset-categories.index');
            Route::post('/asset-categories', [AssetCategoryController::class, 'store'])->name('asset-categories.store');
            Route::patch('/asset-categories/{category}', [AssetCategoryController::class, 'update'])->name('asset-categories.update');
            Route::delete('/asset-categories/{category}', [AssetCategoryController::class, 'destroy'])->name('asset-categories.destroy');

            Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
            Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
            Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
            Route::patch('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
            Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
            Route::post('/assets/{asset}/maintenance', [AssetController::class, 'maintenance'])->name('assets.maintenance');
            Route::post('/assets/{asset}/restore', [AssetController::class, 'restore'])->name('assets.restore');
            Route::post('/assets/{asset}/retire', [AssetController::class, 'retire'])->name('assets.retire');
            Route::post('/assets/{asset}/declare-lost', [AssetController::class, 'declareLost'])->name('assets.declare-lost');
            Route::post('/assets/{asset}/assign', [AssetController::class, 'assign'])->name('assets.assign');
            Route::post('/assets/{asset}/return', [AssetController::class, 'returnCustody'])->name('assets.return');

            Route::get('/asset-custodies', [AssetCustodyController::class, 'index'])->name('asset-custodies.index');
            Route::get('/asset-custodies/{custody}', [AssetCustodyController::class, 'show'])->name('asset-custodies.show');
            Route::get('/my-custodies', [MyCustodyController::class, 'index'])->name('my-custodies.index');

            Route::get('/seasons', [SeasonController::class, 'index'])->name('seasons.index');
            Route::post('/seasons', [SeasonController::class, 'store'])->name('seasons.store');
            Route::get('/seasons/{season}', [SeasonController::class, 'show'])->name('seasons.show');
            Route::patch('/seasons/{season}', [SeasonController::class, 'update'])->name('seasons.update');
            Route::delete('/seasons/{season}', [SeasonController::class, 'destroy'])->name('seasons.destroy');
            Route::post('/seasons/{season}/activate', [SeasonController::class, 'activate'])->name('seasons.activate');
            Route::post('/seasons/{season}/close', [SeasonController::class, 'close'])->name('seasons.close');
            Route::post('/seasons/{season}/reopen', [SeasonController::class, 'reopen'])->name('seasons.reopen');
            Route::get('/seasons/{season}/closing-readiness', [SeasonController::class, 'closingReadiness'])->name('seasons.closing-readiness');

            Route::get('/pilgrims', [PilgrimController::class, 'index'])->name('pilgrims.index');
            Route::post('/pilgrims', [PilgrimController::class, 'store'])->name('pilgrims.store');
            Route::get('/pilgrims/{pilgrim}', [PilgrimController::class, 'show'])->name('pilgrims.show');
            Route::patch('/pilgrims/{pilgrim}', [PilgrimController::class, 'update'])->name('pilgrims.update');
            Route::delete('/pilgrims/{pilgrim}', [PilgrimController::class, 'destroy'])->name('pilgrims.destroy');
            Route::post('/pilgrims/{pilgrim}/identity-documents', [PilgrimController::class, 'storeDocument'])->name('pilgrims.identity-documents.store');
            Route::patch('/pilgrim-identity-documents/{identity_document}', [PilgrimController::class, 'updateDocument'])->name('pilgrim-identity-documents.update');
            Route::delete('/pilgrim-identity-documents/{identity_document}', [PilgrimController::class, 'destroyDocument'])->name('pilgrim-identity-documents.destroy');

            // Transportation tenant-level registry (no season context).
            Route::get('/transport-points', [TransportPointController::class, 'index'])->name('transport-points.index');
            Route::post('/transport-points', [TransportPointController::class, 'store'])->name('transport-points.store');
            Route::get('/transport-points/{transport_point}', [TransportPointController::class, 'show'])->name('transport-points.show');
            Route::patch('/transport-points/{transport_point}', [TransportPointController::class, 'update'])->name('transport-points.update');
            Route::delete('/transport-points/{transport_point}', [TransportPointController::class, 'destroy'])->name('transport-points.destroy');
            Route::post('/transport-points/{transport_point}/activate', [TransportPointController::class, 'activate'])->name('transport-points.activate');
            Route::post('/transport-points/{transport_point}/deactivate', [TransportPointController::class, 'deactivate'])->name('transport-points.deactivate');

            Route::get('/transport-vehicles', [TransportVehicleController::class, 'index'])->name('transport-vehicles.index');
            Route::post('/transport-vehicles', [TransportVehicleController::class, 'store'])->name('transport-vehicles.store');
            Route::get('/transport-vehicles/{transport_vehicle}', [TransportVehicleController::class, 'show'])->name('transport-vehicles.show');
            Route::patch('/transport-vehicles/{transport_vehicle}', [TransportVehicleController::class, 'update'])->name('transport-vehicles.update');
            Route::delete('/transport-vehicles/{transport_vehicle}', [TransportVehicleController::class, 'destroy'])->name('transport-vehicles.destroy');
            Route::post('/transport-vehicles/{transport_vehicle}/activate', [TransportVehicleController::class, 'activate'])->name('transport-vehicles.activate');
            Route::post('/transport-vehicles/{transport_vehicle}/deactivate', [TransportVehicleController::class, 'deactivate'])->name('transport-vehicles.deactivate');
            Route::put('/transport-vehicles/{transport_vehicle}/links', [TransportVehicleController::class, 'links'])->name('transport-vehicles.links');

            Route::get('/transport-external-drivers', [TransportExternalDriverController::class, 'index'])->name('transport-external-drivers.index');
            Route::post('/transport-external-drivers', [TransportExternalDriverController::class, 'store'])->name('transport-external-drivers.store');
            Route::get('/transport-external-drivers/{external_driver}', [TransportExternalDriverController::class, 'show'])->name('transport-external-drivers.show');
            Route::patch('/transport-external-drivers/{external_driver}', [TransportExternalDriverController::class, 'update'])->name('transport-external-drivers.update');
            Route::delete('/transport-external-drivers/{external_driver}', [TransportExternalDriverController::class, 'destroy'])->name('transport-external-drivers.destroy');
            Route::post('/transport-external-drivers/{external_driver}/activate', [TransportExternalDriverController::class, 'activate'])->name('transport-external-drivers.activate');
            Route::post('/transport-external-drivers/{external_driver}/deactivate', [TransportExternalDriverController::class, 'deactivate'])->name('transport-external-drivers.deactivate');

            // Season-scoped resources: the season comes from the Season Context
            // (X-Season-Id header, default = Active season), never from payloads.
            Route::middleware('season.context')->group(function (): void {
                Route::get('/season-context', [SeasonContextController::class, 'show'])->name('season-context.show');

                Route::get('/season-pilgrims', [SeasonPilgrimController::class, 'index'])->name('season-pilgrims.index');
                Route::post('/season-pilgrims', [SeasonPilgrimController::class, 'store'])->name('season-pilgrims.store');
                Route::get('/season-pilgrims/{season_pilgrim}', [SeasonPilgrimController::class, 'show'])->name('season-pilgrims.show');
                Route::delete('/season-pilgrims/{season_pilgrim}', [SeasonPilgrimController::class, 'destroy'])->name('season-pilgrims.destroy');
                Route::post('/season-pilgrims/{season_pilgrim}/activate', [SeasonPilgrimController::class, 'activate'])->name('season-pilgrims.activate');
                Route::post('/season-pilgrims/{season_pilgrim}/complete', [SeasonPilgrimController::class, 'complete'])->name('season-pilgrims.complete');
                Route::post('/season-pilgrims/{season_pilgrim}/cancel', [SeasonPilgrimController::class, 'cancel'])->name('season-pilgrims.cancel');
                Route::post('/season-pilgrims/{season_pilgrim}/snapshot/refresh', [SeasonPilgrimController::class, 'refreshSnapshot'])->name('season-pilgrims.snapshot.refresh');
                Route::post('/season-pilgrims/{season_pilgrim}/snapshot/correct', [SeasonPilgrimController::class, 'correctSnapshot'])->name('season-pilgrims.snapshot.correct');
                Route::put('/season-pilgrims/{season_pilgrim}/program', [SeasonPilgrimController::class, 'changeProgram'])->name('season-pilgrims.program');
                Route::put('/season-pilgrims/{season_pilgrim}/pilgrim-group', [SeasonPilgrimController::class, 'assignPilgrimGroup'])->name('season-pilgrims.pilgrim-group');
                Route::put('/season-pilgrims/{season_pilgrim}/family-group', [SeasonPilgrimController::class, 'setFamilyGroup'])->name('season-pilgrims.family-group');

                Route::get('/season-programs', [SeasonProgramController::class, 'index'])->name('season-programs.index');
                Route::post('/season-programs', [SeasonProgramController::class, 'store'])->name('season-programs.store');
                Route::get('/season-programs/{season_program}', [SeasonProgramController::class, 'show'])->name('season-programs.show');
                Route::patch('/season-programs/{season_program}', [SeasonProgramController::class, 'update'])->name('season-programs.update');
                Route::delete('/season-programs/{season_program}', [SeasonProgramController::class, 'destroy'])->name('season-programs.destroy');
                Route::post('/season-programs/{season_program}/activate', [SeasonProgramController::class, 'activate'])->name('season-programs.activate');
                Route::post('/season-programs/{season_program}/deactivate', [SeasonProgramController::class, 'deactivate'])->name('season-programs.deactivate');

                Route::get('/season-family-groups', [SeasonFamilyGroupController::class, 'index'])->name('season-family-groups.index');
                Route::post('/season-family-groups', [SeasonFamilyGroupController::class, 'store'])->name('season-family-groups.store');
                Route::get('/season-family-groups/{family_group}', [SeasonFamilyGroupController::class, 'show'])->name('season-family-groups.show');
                Route::patch('/season-family-groups/{family_group}', [SeasonFamilyGroupController::class, 'update'])->name('season-family-groups.update');
                Route::delete('/season-family-groups/{family_group}', [SeasonFamilyGroupController::class, 'destroy'])->name('season-family-groups.destroy');
                Route::put('/season-family-groups/{family_group}/primary-member', [SeasonFamilyGroupController::class, 'setPrimaryMember'])->name('season-family-groups.primary-member');

                Route::get('/season-pilgrim-groups', [SeasonPilgrimGroupController::class, 'index'])->name('season-pilgrim-groups.index');
                Route::post('/season-pilgrim-groups', [SeasonPilgrimGroupController::class, 'store'])->name('season-pilgrim-groups.store');
                Route::get('/season-pilgrim-groups/{pilgrim_group}', [SeasonPilgrimGroupController::class, 'show'])->name('season-pilgrim-groups.show');
                Route::patch('/season-pilgrim-groups/{pilgrim_group}', [SeasonPilgrimGroupController::class, 'update'])->name('season-pilgrim-groups.update');
                Route::delete('/season-pilgrim-groups/{pilgrim_group}', [SeasonPilgrimGroupController::class, 'destroy'])->name('season-pilgrim-groups.destroy');
                Route::post('/season-pilgrim-groups/{pilgrim_group}/supervisors', [SeasonPilgrimGroupController::class, 'assignSupervisor'])->name('season-pilgrim-groups.supervisors.store');
                Route::post('/season-pilgrim-groups/{pilgrim_group}/supervisors/{supervisor}/end', [SeasonPilgrimGroupController::class, 'endSupervisor'])->name('season-pilgrim-groups.supervisors.end');

                // Transportation (season-scoped).
                Route::get('/transport-season-vehicles', [TransportSeasonVehicleController::class, 'index'])->name('transport-season-vehicles.index');
                Route::post('/transport-season-vehicles', [TransportSeasonVehicleController::class, 'store'])->name('transport-season-vehicles.store');
                Route::get('/transport-season-vehicles/{season_vehicle}', [TransportSeasonVehicleController::class, 'show'])->name('transport-season-vehicles.show');
                Route::patch('/transport-season-vehicles/{season_vehicle}', [TransportSeasonVehicleController::class, 'update'])->name('transport-season-vehicles.update');
                Route::delete('/transport-season-vehicles/{season_vehicle}', [TransportSeasonVehicleController::class, 'destroy'])->name('transport-season-vehicles.destroy');
                Route::put('/transport-season-vehicles/{season_vehicle}/default-crew', [TransportSeasonVehicleController::class, 'defaultCrew'])->name('transport-season-vehicles.default-crew');
                Route::post('/transport-season-vehicles/{season_vehicle}/activate', [TransportSeasonVehicleController::class, 'activate'])->name('transport-season-vehicles.activate');
                Route::post('/transport-season-vehicles/{season_vehicle}/deactivate', [TransportSeasonVehicleController::class, 'deactivate'])->name('transport-season-vehicles.deactivate');

                Route::get('/transport-entitlements', [TransportEntitlementController::class, 'index'])->name('transport-entitlements.index');
                Route::post('/transport-entitlements', [TransportEntitlementController::class, 'store'])->name('transport-entitlements.store');
                Route::get('/transport-entitlements/{entitlement}', [TransportEntitlementController::class, 'show'])->name('transport-entitlements.show');
                Route::patch('/transport-entitlements/{entitlement}', [TransportEntitlementController::class, 'update'])->name('transport-entitlements.update');
                Route::delete('/transport-entitlements/{entitlement}', [TransportEntitlementController::class, 'destroy'])->name('transport-entitlements.destroy');

                Route::get('/transport-movements', [TransportMovementController::class, 'index'])->name('transport-movements.index');
                Route::post('/transport-movements', [TransportMovementController::class, 'store'])->name('transport-movements.store');
                Route::get('/transport-movements/{movement}', [TransportMovementController::class, 'show'])->name('transport-movements.show');
                Route::patch('/transport-movements/{movement}', [TransportMovementController::class, 'update'])->name('transport-movements.update');
                Route::post('/transport-movements/{movement}/plan', [TransportMovementController::class, 'plan'])->name('transport-movements.plan');
                Route::post('/transport-movements/{movement}/start', [TransportMovementController::class, 'start'])->name('transport-movements.start');
                Route::post('/transport-movements/{movement}/complete', [TransportMovementController::class, 'complete'])->name('transport-movements.complete');
                Route::post('/transport-movements/{movement}/cancel', [TransportMovementController::class, 'cancel'])->name('transport-movements.cancel');
                Route::get('/transport-movements/{movement}/timeline', [TransportMovementController::class, 'timeline'])->name('transport-movements.timeline');
                Route::post('/transport-movements/{movement}/runs', [TransportMovementController::class, 'storeRun'])->name('transport-movements.runs.store');

                Route::get('/transport-runs/{run}', [TransportRunController::class, 'show'])->name('transport-runs.show');
                Route::patch('/transport-runs/{run}', [TransportRunController::class, 'update'])->name('transport-runs.update');
                Route::post('/transport-runs/{run}/start-boarding', [TransportRunController::class, 'startBoarding'])->name('transport-runs.start-boarding');
                Route::post('/transport-runs/{run}/depart', [TransportRunController::class, 'depart'])->name('transport-runs.depart');
                Route::post('/transport-runs/{run}/arrive', [TransportRunController::class, 'arrive'])->name('transport-runs.arrive');
                Route::post('/transport-runs/{run}/complete', [TransportRunController::class, 'complete'])->name('transport-runs.complete');
                Route::post('/transport-runs/{run}/cancel', [TransportRunController::class, 'cancel'])->name('transport-runs.cancel');
                Route::put('/transport-runs/{run}/vehicle', [TransportRunController::class, 'assignVehicle'])->name('transport-runs.vehicle');
                Route::put('/transport-runs/{run}/driver', [TransportRunController::class, 'assignDriver'])->name('transport-runs.driver');
                Route::put('/transport-runs/{run}/supervisor', [TransportRunController::class, 'assignSupervisor'])->name('transport-runs.supervisor');
                Route::get('/transport-runs/{run}/passengers', [TransportRunController::class, 'passengers'])->name('transport-runs.passengers.index');
                Route::post('/transport-runs/{run}/passengers', [TransportRunController::class, 'addPassengers'])->name('transport-runs.passengers.store');
                Route::post('/transport-runs/{run}/passengers/board', [TransportRunController::class, 'board'])->name('transport-runs.passengers.board');
                Route::post('/transport-runs/{run}/passengers/no-show', [TransportRunController::class, 'noShow'])->name('transport-runs.passengers.no-show');
                Route::post('/transport-runs/{run}/passengers/arrive', [TransportRunController::class, 'arrivePassengers'])->name('transport-runs.passengers.arrive');

                Route::post('/transport-run-passengers/{passenger}/cancel', [TransportPassengerController::class, 'cancel'])->name('transport-run-passengers.cancel');
                Route::post('/transport-run-passengers/{passenger}/entitlement-override', [TransportPassengerController::class, 'overrideEntitlement'])->name('transport-run-passengers.entitlement-override');
                Route::post('/transport-timeline-events/{timeline_event}/correct', [TransportTimelineController::class, 'correct'])->name('transport-timeline-events.correct');
                Route::get('/transport-review-items', [TransportReviewItemController::class, 'index'])->name('transport-review-items.index');
                Route::post('/transport-review-items/{review_item}/resolve', [TransportReviewItemController::class, 'resolve'])->name('transport-review-items.resolve');
            });

            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
            Route::post('/notifications/read-all', [NotificationController::class, 'markReadAll'])->name('notifications.read-all');
            Route::get('/notifications/{notification}', [NotificationController::class, 'show'])->name('notifications.show');
            Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

            Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
        });
    });
});
