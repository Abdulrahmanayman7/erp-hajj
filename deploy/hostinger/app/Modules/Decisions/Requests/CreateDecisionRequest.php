<?php

namespace App\Modules\Decisions\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class CreateDecisionRequest extends FormRequest
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
        $hasSource = $this->filled('source_recommendation_id');

        return [
            'source_recommendation_id' => ['nullable', 'integer', new TenantExists('meeting_recommendations')],
            'title' => [$hasSource ? 'nullable' : 'required', 'string', 'max:255'],
            'body' => [$hasSource ? 'nullable' : 'required', 'string'],
            'notes' => ['nullable', 'string'],
            'organization_unit_id' => ['nullable', 'integer', new TenantExists('organization_units')],
            'issued_by_employee_id' => ['nullable', 'integer', new TenantExists('employees')],
            'responsible_employee_id' => ['nullable', 'integer', new TenantExists('employees')],
            'effective_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
