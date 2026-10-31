<?php

namespace App\Modules\Transportation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBreakdownRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'occurred_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
