<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use App\Modules\SuppliersAndProcurement\Enums\SupplierType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSupplierRequest extends FormRequest
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
            'display_name' => ['required', 'string', 'max:200'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'supplier_type' => ['required', 'string', Rule::enum(SupplierType::class)],
            'country_code' => ['required', 'string', 'size:2'],
            'legal_id_type' => ['nullable', 'string', 'max:32'],
            'legal_id_value' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'distinct'],
        ];
    }
}
