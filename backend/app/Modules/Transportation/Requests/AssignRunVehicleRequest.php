<?php

namespace App\Modules\Transportation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignRunVehicleRequest extends FormRequest
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
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
