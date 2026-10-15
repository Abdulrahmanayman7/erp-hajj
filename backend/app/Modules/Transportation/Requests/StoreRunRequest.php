<?php

namespace App\Modules\Transportation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRunRequest extends FormRequest
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
            'transport_season_vehicle_id' => ['required', 'integer'],
            'capacity_override' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
