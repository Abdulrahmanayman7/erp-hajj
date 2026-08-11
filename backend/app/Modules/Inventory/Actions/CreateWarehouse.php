<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\InventoryReferenceValidator;
use App\Modules\Inventory\Support\WarehouseNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateWarehouse
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly WarehouseNumberGenerator $numbers,
        private readonly InventoryReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): Warehouse
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $data, $request, $tenant): Warehouse {
            $unit = $this->references->resolveAssignableOrganizationUnit(
                array_key_exists('organization_unit_id', $data) && $data['organization_unit_id'] !== null
                    ? (int) $data['organization_unit_id']
                    : null,
            );
            $employee = $this->references->resolveAssignableEmployee(
                array_key_exists('responsible_employee_id', $data) && $data['responsible_employee_id'] !== null
                    ? (int) $data['responsible_employee_id']
                    : null,
            );

            $warehouse = new Warehouse([
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?? null,
                'location' => $data['location'] ?? null,
                'organization_unit_id' => $unit?->id,
                'responsible_employee_id' => $employee?->id,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            $warehouse->warehouse_number = $this->numbers->next();
            $warehouse->save();

            $this->security->record(AuthorizationSecurityEvent::WAREHOUSE_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'warehouse_id' => $warehouse->id,
                'warehouse_number' => $warehouse->warehouse_number,
                'name' => $warehouse->name,
            ], $request);

            return $warehouse->load(['organizationUnit', 'responsibleEmployee', 'createdBy']);
        });
    }
}
