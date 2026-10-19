<?php

namespace App\Modules\Accommodation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccommodationConfirmFamilyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Frozen Gate C payload: stated Foundation family group + draft assignment ids.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'season_family_group_id' => ['required', 'integer', 'min:1'],
            'assignment_ids' => ['required', 'array', 'min:1'],
            'assignment_ids.*' => ['integer', 'distinct', 'min:1'],
            'booking_status' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'season_id' => ['prohibited'],
        ];
    }
}
