<?php

namespace App\Modules\RisksAndComplaints\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class EscalateComplaintRequest extends FormRequest
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
            'escalated_to_employee_id' => ['required', 'integer', new TenantExists('employees')],
            'escalate_reason' => ['required', 'string'],
        ];
    }
}
