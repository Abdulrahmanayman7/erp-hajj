<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use App\Modules\SuppliersAndProcurement\Enums\SupplierType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
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
            'display_name' => ['sometimes', 'required', 'string', 'max:200'],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'supplier_type' => ['sometimes', 'required', 'string', Rule::enum(SupplierType::class)],
            'country_code' => ['sometimes', 'required', 'string', 'size:2'],
            'legal_id_type' => ['sometimes', 'nullable', 'string', 'max:32'],
            'legal_id_value' => ['sometimes', 'nullable', 'string', 'max:64'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'distinct'],
        ];
    }
}
