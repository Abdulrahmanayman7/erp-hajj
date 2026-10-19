<?php

namespace App\Modules\Accommodation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccommodationSeasonFacilityRequest extends FormRequest
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
            'facility_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer'],
            'audience' => ['sometimes', 'nullable', 'string', Rule::in(['male', 'female', 'family'])],
            'is_active' => ['sometimes', 'boolean'],
            'tenant_id' => ['prohibited'],
            'season_id' => ['prohibited'],
        ];
    }
}
