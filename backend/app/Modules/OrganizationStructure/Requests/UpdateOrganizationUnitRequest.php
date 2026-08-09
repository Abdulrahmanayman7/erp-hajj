<?php

namespace App\Modules\OrganizationStructure\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationUnitRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'type' => ['sometimes', 'required', 'string', Rule::in(OrganizationUnitType::values())],
            'manager_user_id' => ['sometimes', 'nullable', 'integer', new TenantExists('users')],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'code' => ['prohibited'],
            'parent_id' => ['prohibited'],
            'status' => ['prohibited'],
            'tenant_id' => ['prohibited'],
        ];
    }
}
