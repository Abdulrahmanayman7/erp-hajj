<?php

namespace App\Modules\OrganizationStructure\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use App\Core\Tenancy\Validation\TenantUnique;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrganizationUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z][A-Z0-9_]*$/',
                new TenantUnique('organization_units', 'code'),
            ],
            'type' => ['required', 'string', Rule::in(OrganizationUnitType::values())],
            'parent_id' => ['nullable', 'integer', new TenantExists('organization_units')],
            'manager_user_id' => ['nullable', 'integer', new TenantExists('users')],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'status' => ['sometimes', 'string', Rule::in(OrganizationUnitStatus::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'رمز الوحدة يجب أن يبدأ بحرف إنجليزي كبير ويتكون من أحرف وأرقام وشرطة سفلية.',
            'code.unique' => 'رمز الوحدة التنظيمية مستخدم مسبقاً.',
        ];
    }
}
