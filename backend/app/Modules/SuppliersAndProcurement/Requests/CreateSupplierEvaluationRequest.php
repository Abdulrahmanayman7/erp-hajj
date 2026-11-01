<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use App\Modules\SuppliersAndProcurement\Enums\EvaluationKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSupplierEvaluationRequest extends FormRequest
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
            'supplier_id' => ['required', 'integer'],
            'supplier_category_id' => ['required', 'integer'],
            'evaluation_kind' => ['required', 'string', Rule::in(array_column(EvaluationKind::cases(), 'value'))],
            'season_id' => ['nullable', 'integer'],
            'monitoring_recommended' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'scores' => ['sometimes', 'array'],
            'scores.*.criterion_id' => ['required_with:scores', 'integer'],
            'scores.*.score' => ['required_with:scores', 'numeric', 'between:0,100'],
            'scores.*.note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
