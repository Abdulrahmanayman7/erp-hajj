<?php

namespace App\Modules\Transportation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PassengerOutcomeRequest extends FormRequest
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
            'passenger_ids' => ['required', 'array', 'min:1', 'max:500'],
            'passenger_ids.*' => ['integer', 'distinct'],
            'occurred_at' => ['sometimes', 'nullable', 'date'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
