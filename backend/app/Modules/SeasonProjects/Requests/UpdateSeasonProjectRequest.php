<?php

namespace App\Modules\SeasonProjects\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSeasonProjectRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'owner_employee_id' => ['sometimes', 'nullable', 'integer'],
            'budget_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency_code' => ['sometimes', 'nullable', 'string', 'size:3'],
        ];
    }
}
