<?php

namespace App\Modules\Inventory\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use App\Core\Tenancy\Validation\TenantUnique;
use App\Modules\Inventory\Enums\InventoryUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', new TenantExists('inventory_categories')],
            'unit' => ['required', 'string', Rule::in(InventoryUnit::values())],
            'barcode' => ['nullable', 'string', 'max:64', new TenantUnique('inventory_items', 'barcode')],
            'minimum_stock' => ['nullable', 'numeric', 'decimal:0,3', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
