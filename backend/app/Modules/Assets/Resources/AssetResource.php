<?php

namespace App\Modules\Assets\Resources;

use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetCustody;
use App\Modules\Assets\Models\AssetStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Asset
 */
class AssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Asset $asset */
        $asset = $this->resource;

        $payload = [
            'id' => $asset->id,
            'asset_number' => $asset->asset_number,
            'name' => $asset->name,
            'description' => $asset->description,
            'serial_number' => $asset->serial_number,
            'barcode' => $asset->barcode,
            'status' => $asset->status instanceof \BackedEnum ? $asset->status->value : $asset->status,
            'condition' => $asset->condition instanceof \BackedEnum ? $asset->condition->value : $asset->condition,
            'purchase_value' => $asset->purchase_value,
            'acquisition_date' => $asset->acquisition_date?->toDateString(),
            'notes' => $asset->notes,
            'category' => $this->categoryPayload($asset),
            'warehouse' => $this->warehousePayload($asset),
            'organization_unit' => $this->organizationUnitPayload($asset),
            'current_custody' => $this->currentCustodyPayload($asset),
            'created_by' => $this->createdByPayload($asset),
            'created_at' => $asset->created_at?->toIso8601String(),
            'updated_at' => $asset->updated_at?->toIso8601String(),
        ];

        if ($asset->relationLoaded('transitions')) {
            $payload['transitions'] = $asset->transitions
                ->map(fn (AssetStatusTransition $row) => (new AssetStatusTransitionResource($row))->resolve())
                ->values()
                ->all();
        }

        if ($asset->relationLoaded('custodies')) {
            $payload['custodies'] = $asset->custodies
                ->map(fn (AssetCustody $row) => (new AssetCustodyResource($row))->resolve())
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * @return array{id: int, name: string, is_active: bool}|null
     */
    private function categoryPayload(Asset $asset): ?array
    {
        if (! $asset->relationLoaded('category') || $asset->category === null) {
            return null;
        }

        return [
            'id' => $asset->category->id,
            'name' => $asset->category->name,
            'is_active' => (bool) $asset->category->is_active,
        ];
    }

    /**
     * @return array{id: int, warehouse_number: string|null, name: string}|null
     */
    private function warehousePayload(Asset $asset): ?array
    {
        if (! $asset->relationLoaded('warehouse') || $asset->warehouse === null) {
            return null;
        }

        return [
            'id' => $asset->warehouse->id,
            'warehouse_number' => $asset->warehouse->warehouse_number,
            'name' => $asset->warehouse->name,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string|null}|null
     */
    private function organizationUnitPayload(Asset $asset): ?array
    {
        if (! $asset->relationLoaded('organizationUnit') || $asset->organizationUnit === null) {
            return null;
        }

        return [
            'id' => $asset->organizationUnit->id,
            'name' => $asset->organizationUnit->name,
            'code' => $asset->organizationUnit->code,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     custody_number: string,
     *     assigned_at: string|null,
     *     expected_return_at: string|null,
     *     employee: array{id: int, full_name: string, employee_number: string|null}|null
     * }|null
     */
    private function currentCustodyPayload(Asset $asset): ?array
    {
        if (! $asset->relationLoaded('currentCustody') || $asset->currentCustody === null) {
            return null;
        }

        $custody = $asset->currentCustody;
        $employee = null;
        if ($custody->relationLoaded('employee') && $custody->employee !== null) {
            $employee = [
                'id' => $custody->employee->id,
                'full_name' => $custody->employee->full_name,
                'employee_number' => $custody->employee->employee_number,
            ];
        }

        return [
            'id' => $custody->id,
            'custody_number' => $custody->custody_number,
            'assigned_at' => $custody->assigned_at?->toIso8601String(),
            'expected_return_at' => $custody->expected_return_at?->toIso8601String(),
            'employee' => $employee,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function createdByPayload(Asset $asset): ?array
    {
        if (! $asset->relationLoaded('createdBy') || $asset->createdBy === null) {
            return null;
        }

        return [
            'id' => $asset->createdBy->id,
            'name' => $asset->createdBy->name,
        ];
    }
}
