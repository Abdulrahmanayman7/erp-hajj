<?php

namespace App\Modules\Assets\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Support\AssetReferenceValidator;
use App\Modules\Assets\Support\AssetStatusTransitionRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateAsset
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AssetReferenceValidator $references,
        private readonly AssetStatusTransitionRecorder $transitions,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): Asset
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $data, $request, $tenant): Asset {
            $category = $this->references->resolveAssignableCategory(
                array_key_exists('category_id', $data) && $data['category_id'] !== null
                    ? (int) $data['category_id']
                    : null,
            );
            $warehouse = $this->references->resolveAssignableWarehouse(
                array_key_exists('warehouse_id', $data) && $data['warehouse_id'] !== null
                    ? (int) $data['warehouse_id']
                    : null,
            );
            $unit = $this->references->resolveAssignableOrganizationUnit(
                array_key_exists('organization_unit_id', $data) && $data['organization_unit_id'] !== null
                    ? (int) $data['organization_unit_id']
                    : null,
            );
            $serial = $this->references->assertUniqueSerial(
                array_key_exists('serial_number', $data) ? ($data['serial_number'] !== null ? (string) $data['serial_number'] : null) : null,
            );
            $barcode = $this->references->assertUniqueBarcode(
                array_key_exists('barcode', $data) ? ($data['barcode'] !== null ? (string) $data['barcode'] : null) : null,
            );
            $condition = $this->references->assertCondition(
                isset($data['condition']) ? (string) $data['condition'] : null,
            );

            $asset = new Asset([
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?? null,
                'category_id' => $category?->id,
                'serial_number' => $serial,
                'barcode' => $barcode,
                'condition' => $condition,
                'warehouse_id' => $warehouse?->id,
                'organization_unit_id' => $unit?->id,
                'purchase_value' => $data['purchase_value'] ?? null,
                'acquisition_date' => $data['acquisition_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            $asset->status = AssetStatus::Available;
            $asset->current_custody_id = null;
            $asset->save();

            $this->transitions->record(
                $asset,
                null,
                AssetStatus::Available,
                $actor,
            );

            $this->security->record(AuthorizationSecurityEvent::ASSET_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'asset_id' => $asset->id,
                'asset_number' => $asset->asset_number,
                'name' => $asset->name,
                'status' => $asset->status->value,
            ], $request);

            return $asset->load(['category', 'warehouse', 'organizationUnit', 'currentCustody.employee', 'createdBy']);
        });
    }
}
