<?php

namespace App\Modules\Assets\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use App\Modules\Assets\Enums\AssetCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category_id' => ['sometimes', 'nullable', 'integer', new TenantExists('asset_categories')],
            'serial_number' => ['sometimes', 'nullable', 'string', 'max:120'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'condition' => ['sometimes', 'nullable', 'string', Rule::in(AssetCondition::values())],
            'warehouse_id' => ['sometimes', 'nullable', 'integer', new TenantExists('warehouses')],
            'organization_unit_id' => ['sometimes', 'nullable', 'integer', new TenantExists('organization_units')],
            'purchase_value' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'acquisition_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
