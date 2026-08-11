<?php

namespace App\Modules\Contracts\Requests;

use App\Core\Tenancy\Validation\TenantUnique;
use Illuminate\Foundation\Http\FormRequest;

class CreateContractCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->input('code'))) {
            $trimmed = trim($this->input('code'));
            $this->merge(['code' => $trimmed === '' ? null : $trimmed]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', new TenantUnique('contract_categories', 'name')],
            'code' => ['nullable', 'string', 'max:50', new TenantUnique('contract_categories', 'code')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
