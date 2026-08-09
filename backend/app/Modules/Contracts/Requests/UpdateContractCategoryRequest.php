<?php

namespace App\Modules\Contracts\Requests;

use App\Core\Tenancy\Validation\TenantUnique;
use App\Modules\Contracts\Models\ContractCategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContractCategoryRequest extends FormRequest
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
        /** @var ContractCategory $category */
        $category = $this->route('contract_category');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                (new TenantUnique('contract_categories', 'name'))->ignore($category->id),
            ],
            'code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
