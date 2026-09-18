<?php

namespace App\Modules\Assets\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Assets\Exceptions\AssetDomainException;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Support\AssetReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateAsset
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AssetReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Asset $asset, array $data, Request $request): Asset
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $asset, $data, $request, $tenant): Asset {
            /** @var Asset $locked */
            $locked = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isTerminal()) {
                throw AssetDomainException::immutable();
            }

            if (array_key_exists('category_id', $data)) {
                $category = $this->references->resolveAssignableCategory(
                    $data['category_id'] !== null ? (int) $data['category_id'] : null,
                    $locked->category_id !== null ? (int) $locked->category_id : null,
                );
                $locked->category_id = $category?->id;
            }

            if (array_key_exists('warehouse_id', $data)) {
                $warehouse = $this->references->resolveAssignableWarehouse(
                    $data['warehouse_id'] !== null ? (int) $data['warehouse_id'] : null,
                    $locked->warehouse_id !== null ? (int) $locked->warehouse_id : null,
                );
                $locked->warehouse_id = $warehouse?->id;
            }

            if (array_key_exists('organization_unit_id', $data)) {
                $unit = $this->references->resolveAssignableOrganizationUnit(
                    $data['organization_unit_id'] !== null ? (int) $data['organization_unit_id'] : null,
                    $locked->organization_unit_id !== null ? (int) $locked->organization_unit_id : null,
                );
                $locked->organization_unit_id = $unit?->id;
            }

            if (array_key_exists('serial_number', $data)) {
                $locked->serial_number = $this->references->assertUniqueSerial(
                    $data['serial_number'] !== null ? (string) $data['serial_number'] : null,
                    (int) $locked->id,
                );
            }

            if (array_key_exists('barcode', $data)) {
                $locked->barcode = $this->references->assertUniqueBarcode(
                    $data['barcode'] !== null ? (string) $data['barcode'] : null,
                    (int) $locked->id,
                );
            }

            if (array_key_exists('condition', $data)) {
                $locked->condition = $this->references->assertCondition(
                    $data['condition'] !== null ? (string) $data['condition'] : null,
                );
            }

            if (array_key_exists('name', $data)) {
                $locked->name = trim((string) $data['name']);
            }
            if (array_key_exists('description', $data)) {
                $locked->description = $data['description'];
            }
            if (array_key_exists('purchase_value', $data)) {
                $locked->purchase_value = $data['purchase_value'];
            }
            if (array_key_exists('acquisition_date', $data)) {
                $locked->acquisition_date = $data['acquisition_date'];
            }
            if (array_key_exists('notes', $data)) {
                $locked->notes = $data['notes'];
            }

            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::ASSET_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'asset_id' => $locked->id,
                'asset_number' => $locked->asset_number,
            ], $request);

            return $locked->load(['category', 'warehouse', 'organizationUnit', 'currentCustody.employee', 'createdBy']);
        });
    }
}
