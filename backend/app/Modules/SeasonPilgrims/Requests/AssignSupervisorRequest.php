<?php

namespace App\Modules\SeasonPilgrims\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use App\Modules\SeasonPilgrims\Enums\SupervisorRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSupervisorRequest extends FormRequest
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
            'role' => ['required', 'string', Rule::in(SupervisorRole::values())],
        ];
    }
}
