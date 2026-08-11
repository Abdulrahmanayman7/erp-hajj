<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\InventoryReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateWarehouse
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InventoryReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Warehouse $warehouse, array $data, Request $request): Warehouse
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $warehouse, $data, $request, $tenant): Warehouse {
            $locked = Warehouse::query()->whereKey($warehouse->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('organization_unit_id', $data)) {
                $unit = $this->references->resolveAssignableOrganizationUnit(
                    $data['organization_unit_id'] !== null ? (int) $data['organization_unit_id'] : null,
                    $locked->organization_unit_id,
                );
                $locked->organization_unit_id = $unit?->id;
            }

            if (array_key_exists('responsible_employee_id', $data)) {
                $employee = $this->references->resolveAssignableEmployee(
                    $data['responsible_employee_id'] !== null ? (int) $data['responsible_employee_id'] : null,
                    $locked->responsible_employee_id,
                );
                $locked->responsible_employee_id = $employee?->id;
            }

            if (array_key_exists('name', $data)) {
                $locked->name = trim((string) $data['name']);
            }
            if (array_key_exists('description', $data)) {
                $locked->description = $data['description'];
            }
            if (array_key_exists('location', $data)) {
                $locked->location = $data['location'];
            }
            if (array_key_exists('notes', $data)) {
                $locked->notes = $data['notes'];
            }

            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::WAREHOUSE_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'warehouse_id' => $locked->id,
                'warehouse_number' => $locked->warehouse_number,
            ], $request);

            return $locked->load(['organizationUnit', 'responsibleEmployee', 'createdBy']);
        });
    }
}
