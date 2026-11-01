<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use App\Core\Tenancy\Validation\TenantUnique;
use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierCategoryRequest extends FormRequest
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
        /** @var SupplierCategory $category */
        $category = $this->route('supplier_category');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                (new TenantUnique('supplier_categories', 'name'))->ignore($category->id),
            ],
            'code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
