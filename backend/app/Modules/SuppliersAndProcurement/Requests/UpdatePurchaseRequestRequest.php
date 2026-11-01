<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseRequestRequest extends FormRequest
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
            'request_kind' => ['sometimes', 'string', Rule::in(['seasonal', 'administrative'])],
            'season_id' => ['nullable', 'integer'],
            'title' => ['sometimes', 'string', 'max:255'],
            'purpose' => ['sometimes', 'string'],
            'priority' => ['sometimes', 'string', Rule::in(['normal', 'urgent', 'emergency'])],
            'urgency_justification' => ['nullable', 'string'],
            'beneficiary_organization_unit_id' => ['sometimes', 'integer'],
            'optional_supplier_id' => ['nullable', 'integer'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ];
    }
}
