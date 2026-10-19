<?php

namespace App\Modules\Accommodation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccommodationRelocateRequest extends FormRequest
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
            'destination_season_unit_id' => ['required', 'integer'],
            'destination_season_position_id' => ['sometimes', 'nullable', 'integer'],
            'occurred_at' => ['sometimes', 'nullable', 'date'],
            'check_in_after' => ['sometimes', 'boolean'],
            'tenant_id' => ['prohibited'],
            'season_id' => ['prohibited'],
            'booking_status' => ['prohibited'],
            'operational_status' => ['prohibited'],
            'relocated_from_assignment_id' => ['prohibited'],
        ];
    }
}