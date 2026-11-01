<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertEvaluationCriterionRequest extends FormRequest
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
        $isPatch = $this->isMethod('PATCH') || $this->isMethod('PUT');

        return [
            'name' => [$isPatch ? 'sometimes' : 'required', 'string', 'max:150'],
            'weight' => [$isPatch ? 'sometimes' : 'required', 'numeric', 'gt:0', 'lte:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
