<?php

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Warehouse
 */
class WarehouseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Warehouse $warehouse */
        $warehouse = $this->resource;

        return [
            'id' => $warehouse->id,
            'warehouse_number' => $warehouse->warehouse_number,
            'name' => $warehouse->name,
            'description' => $warehouse->description,
            'location' => $warehouse->location,
            'is_active' => (bool) $warehouse->is_active,
            'notes' => $warehouse->notes,
            'organization_unit' => $this->organizationUnitPayload($warehouse),
            'responsible_employee' => $this->responsibleEmployeePayload($warehouse),
            'created_by' => $this->createdByPayload($warehouse),
            'created_at' => $warehouse->created_at?->toIso8601String(),
            'updated_at' => $warehouse->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string, code: string|null}|null
     */
    private function organizationUnitPayload(Warehouse $warehouse): ?array
    {
        if (! $warehouse->relationLoaded('organizationUnit') || $warehouse->organizationUnit === null) {
            return null;
        }

        return [
            'id' => $warehouse->organizationUnit->id,
            'name' => $warehouse->organizationUnit->name,
            'code' => $warehouse->organizationUnit->code,
        ];
    }

    /**
     * @return array{id: int, full_name: string, employee_number: string|null}|null
     */
    private function responsibleEmployeePayload(Warehouse $warehouse): ?array
    {
        if (! $warehouse->relationLoaded('responsibleEmployee') || $warehouse->responsibleEmployee === null) {
            return null;
        }

        return [
            'id' => $warehouse->responsibleEmployee->id,
            'full_name' => $warehouse->responsibleEmployee->full_name,
            'employee_number' => $warehouse->responsibleEmployee->employee_number,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function createdByPayload(Warehouse $warehouse): ?array
    {
        if (! $warehouse->relationLoaded('createdBy') || $warehouse->createdBy === null) {
            return null;
        }

        return [
            'id' => $warehouse->createdBy->id,
            'name' => $warehouse->createdBy->name,
        ];
    }
}
