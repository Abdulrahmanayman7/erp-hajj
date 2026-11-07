<?php

namespace App\Modules\SeasonProjects\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeasonProjectRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'owner_employee_id' => ['nullable', 'integer'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
        ];
    }
}
