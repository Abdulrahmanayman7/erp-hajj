<?php

namespace App\Modules\RisksAndComplaints\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class AssignRiskRequest extends FormRequest
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
            'owner_employee_id' => ['required', 'integer', new TenantExists('employees')],
        ];
    }
}
