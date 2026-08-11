<?php

namespace App\Modules\Inventory\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class TransferStockRequest extends FormRequest
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
            'source_warehouse_id' => ['required', 'integer', new TenantExists('warehouses')],
            'destination_warehouse_id' => ['required', 'integer', new TenantExists('warehouses')],
            'inventory_item_id' => ['required', 'integer', new TenantExists('inventory_items')],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
            'reason' => ['required', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
