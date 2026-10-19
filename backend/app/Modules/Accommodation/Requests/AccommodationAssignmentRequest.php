<?php

namespace App\Modules\Accommodation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccommodationAssignmentRequest extends FormRequest
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
            'season_pilgrim_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer'],
            'season_unit_id' => [$required, 'integer'],
            'season_position_id' => ['sometimes', 'nullable', 'integer'],
            'starts_at' => [$required, 'date'],
            'original_ends_at' => [$required, 'date'],
            'booking_status' => ['prohibited'],
            'operational_status' => ['prohibited'],
            'family_group_id' => ['prohibited'],
            'checkout_source' => ['prohibited'],
            'effective_ends_at' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'season_id' => ['prohibited'],
            'unit_id' => ['prohibited'],
        ];
    }
}
