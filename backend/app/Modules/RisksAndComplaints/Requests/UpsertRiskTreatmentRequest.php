<?php

namespace App\Modules\RisksAndComplaints\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use App\Modules\RisksAndComplaints\Enums\TreatmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertRiskTreatmentRequest extends FormRequest
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
            'plan_body' => ['required', 'string'],
            'owner_employee_id' => ['required', 'integer', new TenantExists('employees')],
            'due_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(array_column(TreatmentStatus::cases(), 'value'))],
        ];
    }
}
