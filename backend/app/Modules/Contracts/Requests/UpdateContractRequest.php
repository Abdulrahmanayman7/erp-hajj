<?php

namespace App\Modules\Contracts\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use App\Modules\Contracts\Enums\CounterpartyKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'contract_category_id' => ['sometimes', 'required', 'integer', new TenantExists('contract_categories')],
            'counterparty_name' => ['sometimes', 'required', 'string', 'max:200'],
            'counterparty_kind' => ['sometimes', 'required', 'string', Rule::in([
                CounterpartyKind::Person->value,
                CounterpartyKind::Organization->value,
                CounterpartyKind::Other->value,
            ])],
            'employee_id' => ['sometimes', 'nullable', 'integer', new TenantExists('employees')],
            'organization_unit_id' => ['sometimes', 'nullable', 'integer', new TenantExists('organization_units')],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'value' => ['sometimes', 'nullable', 'numeric'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
