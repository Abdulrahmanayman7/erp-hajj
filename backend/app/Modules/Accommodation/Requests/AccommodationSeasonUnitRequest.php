<?php

namespace App\Modules\Accommodation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccommodationSeasonUnitRequest extends FormRequest
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
            'season_facility_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer'],
            'unit_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer'],
            'allocation_mode' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', Rule::in(['capacity', 'position'])],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'audience' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', Rule::in(['male', 'female', 'family'])],
            'is_active' => ['sometimes', 'boolean'],
            'tenant_id' => ['prohibited'],
            'season_id' => ['prohibited'],
            'facility_id' => ['prohibited'],
        ];
    }
}
