<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use App\Modules\SuppliersAndProcurement\Enums\EvaluationKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierEvaluationRequest extends FormRequest
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
            'evaluation_kind' => ['sometimes', 'string', Rule::in(array_column(EvaluationKind::cases(), 'value'))],
            'season_id' => ['sometimes', 'nullable', 'integer'],
            'monitoring_recommended' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'scores' => ['sometimes', 'array'],
            'scores.*.criterion_id' => ['required_with:scores', 'integer'],
            'scores.*.score' => ['required_with:scores', 'numeric', 'between:0,100'],
            'scores.*.note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
