<?php

namespace App\Modules\SuppliersAndProcurement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPurchaseRequestFinancialReviewRequest extends FormRequest
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
            'outcome' => ['required', 'string', Rule::in(['cleared', 'not_cleared', 'cleared_with_conditions'])],
            'justification' => ['required', 'string', 'min:10'],
            'version_no' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
