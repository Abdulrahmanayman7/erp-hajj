<?php

namespace App\Modules\Gps\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGpsDeviceBindingRequest extends FormRequest
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
            'external_device_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'transport_season_vehicle_id' => ['sometimes', 'integer', 'min:1'],
            'gps_provider_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
