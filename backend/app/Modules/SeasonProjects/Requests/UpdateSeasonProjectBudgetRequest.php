<?php

namespace App\Modules\SeasonProjects\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSeasonProjectBudgetRequest extends FormRequest
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
            'budget_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
        ];
    }
}
