<?php

namespace App\Modules\Assets\Resources;

use App\Modules\Assets\Models\AssetCustody;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssetCustody
 */
class AssetCustodyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AssetCustody $custody */
        $custody = $this->resource;

        return [
            'id' => $custody->id,
            'custody_number' => $custody->custody_number,
            'status' => $custody->status instanceof \BackedEnum ? $custody->status->value : $custody->status,
            'assigned_at' => $custody->assigned_at?->toIso8601String(),
            'expected_return_at' => $custody->expected_return_at?->toIso8601String(),
            'returned_at' => $custody->returned_at?->toIso8601String(),
            'condition_at_assignment' => $custody->condition_at_assignment instanceof \BackedEnum
                ? $custody->condition_at_assignment->value
                : $custody->condition_at_assignment,
            'condition_at_return' => $custody->condition_at_return instanceof \BackedEnum
                ? $custody->condition_at_return->value
                : $custody->condition_at_return,
            'assignment_notes' => $custody->assignment_notes,
            'return_notes' => $custody->return_notes,
            'is_overdue' => $custody->isOverdue(),
            'asset' => $this->assetPayload($custody),
            'employee' => $this->employeePayload($custody),
            'assigned_by' => $this->userPayload($custody, 'assignedBy'),
            'returned_by' => $this->userPayload($custody, 'returnedBy'),
            'created_at' => $custody->created_at?->toIso8601String(),
            'updated_at' => $custody->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, asset_number: string, name: string, status: string|null}|null
     */
    private function assetPayload(AssetCustody $custody): ?array
    {
        if (! $custody->relationLoaded('asset') || $custody->asset === null) {
            return null;
        }

        $asset = $custody->asset;

        return [
            'id' => $asset->id,
            'asset_number' => $asset->asset_number,
            'name' => $asset->name,
            'status' => $asset->status instanceof \BackedEnum ? $asset->status->value : $asset->status,
        ];
    }

    /**
     * @return array{id: int, full_name: string, employee_number: string|null}|null
     */
    private function employeePayload(AssetCustody $custody): ?array
    {
        if (! $custody->relationLoaded('employee') || $custody->employee === null) {
            return null;
        }

        return [
            'id' => $custody->employee->id,
            'full_name' => $custody->employee->full_name,
            'employee_number' => $custody->employee->employee_number,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userPayload(AssetCustody $custody, string $relation): ?array
    {
        if (! $custody->relationLoaded($relation) || $custody->{$relation} === null) {
            return null;
        }

        return [
            'id' => $custody->{$relation}->id,
            'name' => $custody->{$relation}->name,
        ];
    }
}
