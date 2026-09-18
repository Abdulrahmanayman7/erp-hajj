<?php

namespace App\Modules\Assets\Requests;

use App\Core\Tenancy\Validation\TenantUnique;
use App\Modules\Assets\Models\AssetCategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetCategoryRequest extends FormRequest
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
        /** @var AssetCategory $category */
        $category = $this->route('category');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                (new TenantUnique('asset_categories', 'name'))->ignore($category->id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
