<?php

namespace App\Modules\RisksAndComplaints\Requests;

use App\Modules\RisksAndComplaints\Enums\RiskClosureOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloseRiskRequest extends FormRequest
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
            'closure_outcome' => ['required', 'string', Rule::in(array_column(RiskClosureOutcome::cases(), 'value'))],
            'close_reason' => ['nullable', 'string'],
        ];
    }
}
