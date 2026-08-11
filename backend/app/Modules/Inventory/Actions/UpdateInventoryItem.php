<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Inventory\Enums\InventoryUnit;
use App\Modules\Inventory\Exceptions\InventoryDomainException;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Support\InventoryQuantity;
use App\Modules\Inventory\Support\InventoryReferenceValidator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateInventoryItem
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InventoryReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, InventoryItem $item, array $data, Request $request): InventoryItem
    {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($actor, $item, $data, $request, $tenant): InventoryItem {
                $locked = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

                if (array_key_exists('category_id', $data)) {
                    $category = $this->references->resolveAssignableCategory(
                        $data['category_id'] !== null && $data['category_id'] !== ''
                            ? (int) $data['category_id']
                            : null,
                        $locked->category_id,
                    );
                    $locked->category_id = $category?->id;
                }

                if (array_key_exists('name', $data)) {
                    $locked->name = trim((string) $data['name']);
                }
                if (array_key_exists('description', $data)) {
                    $locked->description = $data['description'];
                }
                if (array_key_exists('unit', $data)) {
                    $locked->unit = InventoryUnit::from((string) $data['unit']);
                }
                if (array_key_exists('barcode', $data)) {
                    $locked->barcode = $data['barcode'] !== null && $data['barcode'] !== ''
                        ? trim((string) $data['barcode'])
                        : null;
                }
                if (array_key_exists('minimum_stock', $data)) {
                    $locked->minimum_stock = InventoryQuantity::validateNonNegative($data['minimum_stock']);
                }
                if (array_key_exists('notes', $data)) {
                    $locked->notes = $data['notes'];
                }

                $locked->save();

                $this->security->record(AuthorizationSecurityEvent::INVENTORY_ITEM_UPDATED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'inventory_item_id' => $locked->id,
                    'item_number' => $locked->item_number,
                ], $request);

                return $locked->load(['category', 'createdBy']);
            });
        } catch (QueryException $e) {
            if ($this->isBarcodeViolation($e)) {
                throw InventoryDomainException::barcodeTaken();
            }
            throw $e;
        }
    }

    private function isBarcodeViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'inventory_items_tenant_barcode_unique')
            || (str_contains($message, 'barcode') && str_contains($message, 'Duplicate'));
    }
}
