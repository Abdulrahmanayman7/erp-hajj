<?php

namespace App\Modules\Inventory\Requests;

use App\Core\Tenancy\Validation\TenantUnique;
use App\Modules\Inventory\Models\InventoryCategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryCategoryRequest extends FormRequest
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
        /** @var InventoryCategory $category */
        $category = $this->route('category');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                (new TenantUnique('inventory_categories', 'name'))->ignore($category->id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
