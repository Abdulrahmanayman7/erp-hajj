<?php

namespace App\Modules\Assets\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use App\Modules\Assets\Enums\AssetCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
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
            'category_id' => ['nullable', 'integer', new TenantExists('asset_categories')],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'condition' => ['nullable', 'string', Rule::in(AssetCondition::values())],
            'warehouse_id' => ['nullable', 'integer', new TenantExists('warehouses')],
            'organization_unit_id' => ['nullable', 'integer', new TenantExists('organization_units')],
            'purchase_value' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'acquisition_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
