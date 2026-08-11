<?php

namespace App\Modules\Tasks\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class AssignTaskRequest extends FormRequest
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
            'assigned_to_employee_id' => ['required', 'integer', new TenantExists('employees')],
            'comment' => ['nullable', 'string'],
        ];
    }
}
