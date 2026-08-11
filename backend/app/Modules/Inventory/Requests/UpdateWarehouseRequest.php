<?php

namespace App\Modules\Inventory\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'location' => ['sometimes', 'nullable', 'string', 'max:500'],
            'organization_unit_id' => ['sometimes', 'nullable', 'integer', new TenantExists('organization_units')],
            'responsible_employee_id' => ['sometimes', 'nullable', 'integer', new TenantExists('employees')],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
