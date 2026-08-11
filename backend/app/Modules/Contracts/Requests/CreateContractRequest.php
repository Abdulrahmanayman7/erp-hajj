<?php

namespace App\Modules\Contracts\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use App\Modules\Contracts\Enums\CounterpartyKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateContractRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'contract_category_id' => ['required', 'integer', new TenantExists('contract_categories')],
            'counterparty_name' => ['required', 'string', 'max:200'],
            'counterparty_kind' => ['nullable', 'string', Rule::in([
                CounterpartyKind::Person->value,
                CounterpartyKind::Organization->value,
                CounterpartyKind::Other->value,
            ])],
            'employee_id' => ['nullable', 'integer', new TenantExists('employees')],
            'organization_unit_id' => ['nullable', 'integer', new TenantExists('organization_units')],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'value' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
