<?php

namespace App\Modules\Inventory\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use App\Modules\Inventory\Enums\MovementDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', new TenantExists('warehouses')],
            'inventory_item_id' => ['required', 'integer', new TenantExists('inventory_items')],
            'direction' => ['required', 'string', Rule::in(array_column(MovementDirection::cases(), 'value'))],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
            // Reason emptiness is enforced in AdjustStock via InventoryReferenceValidator
            // so the API returns INVENTORY_ADJUSTMENT_REASON_REQUIRED (not generic validation).
            'reason' => ['nullable', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
