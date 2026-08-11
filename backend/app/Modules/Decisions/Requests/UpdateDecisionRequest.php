<?php

namespace App\Modules\Decisions\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDecisionRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string'],
            'notes' => ['nullable', 'string'],
            'organization_unit_id' => ['nullable', 'integer', new TenantExists('organization_units')],
            'issued_by_employee_id' => ['nullable', 'integer', new TenantExists('employees')],
            'responsible_employee_id' => ['nullable', 'integer', new TenantExists('employees')],
            'effective_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
