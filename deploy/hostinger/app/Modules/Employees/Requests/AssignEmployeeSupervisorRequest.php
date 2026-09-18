<?php

namespace App\Modules\Employees\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class AssignEmployeeSupervisorRequest extends FormRequest
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
            'supervisor_id' => ['nullable', 'integer', new TenantExists('employees')],
        ];
    }
}
