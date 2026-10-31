<?php

namespace App\Modules\Transportation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteReplacementRequest extends FormRequest
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
            'replacement_season_vehicle_id' => ['required', 'integer', 'min:1'],
            'reason' => ['sometimes', 'nullable', 'string', 'min:3', 'max:1000'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:64'],
            'reassignments' => ['sometimes', 'array'],
            'reassignments.*.run_passenger_id' => ['required_with:reassignments', 'integer', 'min:1'],
            'reassignments.*.target_run_id' => ['required_with:reassignments', 'integer', 'min:1'],
        ];
    }
}
