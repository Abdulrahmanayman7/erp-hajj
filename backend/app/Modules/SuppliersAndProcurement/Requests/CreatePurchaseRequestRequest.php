<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePurchaseRequestRequest extends FormRequest
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
            'request_kind' => ['required', 'string', Rule::in(['seasonal', 'administrative'])],
            'season_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string'],
            'priority' => ['nullable', 'string', Rule::in(['normal', 'urgent', 'emergency'])],
            'urgency_justification' => ['nullable', 'string'],
            'beneficiary_organization_unit_id' => ['required', 'integer'],
            'optional_supplier_id' => ['nullable', 'integer'],
            'currency' => ['nullable', 'string', 'size:3'],
            'lines' => ['nullable', 'array'],
            'lines.*.line_no' => ['nullable', 'integer', 'min:1'],
            'lines.*.description' => ['required_with:lines', 'string'],
            'lines.*.specifications' => ['nullable', 'string'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'gt:0'],
            'lines.*.unit' => ['required_with:lines', 'string', 'max:64'],
            'lines.*.need_date' => ['required_with:lines', 'date'],
            'lines.*.inventory_item_id' => ['nullable', 'integer'],
            'lines.*.unit_estimate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.line_estimate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unpriced_reason' => ['nullable', 'string'],
        ];
    }
}
