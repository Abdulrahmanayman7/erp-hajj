<?php

namespace App\Modules\Transportation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMovementRequest extends FormRequest
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'origin_point_id' => [$required, 'integer'],
            'destination_point_id' => [$required, 'integer'],
            'planned_departure_at' => [$required, 'date'],
            'planned_arrival_at' => [$required, 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
