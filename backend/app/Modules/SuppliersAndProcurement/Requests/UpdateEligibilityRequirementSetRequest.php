<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEligibilityRequirementSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * config_key and version_no are immutable and therefore not accepted.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            ...CreateEligibilityRequirementSetRequest::defRules(required: false),
        ];
    }
}
