<?php

namespace App\Modules\Inventory\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Inventory\Enums\InventoryUnit;
use App\Modules\Inventory\Exceptions\InventoryDomainException;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Support\InventoryItemNumberGenerator;
use App\Modules\Inventory\Support\InventoryQuantity;
use App\Modules\Inventory\Support\InventoryReferenceValidator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateInventoryItem
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InventoryItemNumberGenerator $numbers,
        private readonly InventoryReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): InventoryItem
    {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($actor, $data, $request, $tenant): InventoryItem {
                $category = $this->references->resolveAssignableCategory(
                    array_key_exists('category_id', $data) && $data['category_id'] !== null && $data['category_id'] !== ''
                        ? (int) $data['category_id']
                        : null,
                );

                $minimum = array_key_exists('minimum_stock', $data) && $data['minimum_stock'] !== null
                    ? InventoryQuantity::validateNonNegative($data['minimum_stock'])
                    : '0.000';

                $barcode = array_key_exists('barcode', $data) && $data['barcode'] !== null && $data['barcode'] !== ''
                    ? trim((string) $data['barcode'])
                    : null;

                $item = new InventoryItem([
                    'name' => trim((string) $data['name']),
                    'description' => $data['description'] ?? null,
                    'category_id' => $category?->id,
                    'unit' => InventoryUnit::from((string) $data['unit']),
                    'barcode' => $barcode,
                    'minimum_stock' => $minimum,
                    'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $actor->id,
                ]);
                $item->item_number = $this->numbers->next();
                $item->save();

                $this->security->record(AuthorizationSecurityEvent::INVENTORY_ITEM_CREATED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'inventory_item_id' => $item->id,
                    'item_number' => $item->item_number,
                    'name' => $item->name,
                ], $request);

                return $item->load(['category', 'createdBy']);
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
