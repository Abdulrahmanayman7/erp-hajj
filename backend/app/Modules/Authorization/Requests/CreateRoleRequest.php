<?php

namespace App\Modules\Authorization\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRoleRequest extends FormRequest
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
        $tenantId = $this->user()?->tenant_id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'code' => [
                'nullable',
                'string',
                'max:63',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'code')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
