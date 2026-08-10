<?php

namespace App\Modules\Tasks\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
            'decision_id' => ['nullable', 'integer', new TenantExists('decisions')],
            'organization_unit_id' => ['nullable', 'integer', new TenantExists('organization_units')],
            'assigned_to_employee_id' => ['nullable', 'integer', new TenantExists('employees')],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
