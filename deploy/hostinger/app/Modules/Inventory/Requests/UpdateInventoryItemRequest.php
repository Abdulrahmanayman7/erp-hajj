<?php

namespace App\Modules\Inventory\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use App\Core\Tenancy\Validation\TenantUnique;
use App\Modules\Inventory\Enums\InventoryUnit;
use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryItemRequest extends FormRequest
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
        /** @var InventoryItem $item */
        $item = $this->route('inventory_item');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category_id' => ['sometimes', 'nullable', 'integer', new TenantExists('inventory_categories')],
            'unit' => ['sometimes', 'required', 'string', Rule::in(InventoryUnit::values())],
            'barcode' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
                (new TenantUnique('inventory_items', 'barcode'))->ignore($item->id),
            ],
            'minimum_stock' => ['sometimes', 'nullable', 'numeric', 'decimal:0,3', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
