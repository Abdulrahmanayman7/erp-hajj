<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use App\Core\Tenancy\Validation\TenantExists;
use App\Modules\SuppliersAndProcurement\Enums\SupplierType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEligibilityRequirementSetRequest extends FormRequest
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
            'config_key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'name' => ['required', 'string', 'max:150'],
            ...self::defRules(required: true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defRules(bool $required): array
    {
        return [
            'requirement_defs' => [$required ? 'required' : 'sometimes', 'array', 'min:1', 'max:200'],
            'requirement_defs.*.requirement_key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9_]*$/'],
            'requirement_defs.*.label_ar' => ['required', 'string', 'max:255'],
            'requirement_defs.*.description' => ['nullable', 'string', 'max:2000'],
            'requirement_defs.*.supplier_type' => ['required', 'string', Rule::enum(SupplierType::class)],
            'requirement_defs.*.country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'requirement_defs.*.supplier_category_id' => ['nullable', 'integer', new TenantExists('supplier_categories')],
            'requirement_defs.*.is_mandatory' => ['sometimes', 'boolean'],
            'requirement_defs.*.default_validity_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
