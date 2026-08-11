<?php

namespace App\Modules\Assets\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use App\Modules\Assets\Enums\AssetCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCustodyRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', new TenantExists('employees')],
            'expected_return_at' => ['nullable', 'date'],
            'condition_at_assignment' => ['nullable', 'string', Rule::in(AssetCondition::values())],
            'assignment_notes' => ['nullable', 'string'],
        ];
    }
}
