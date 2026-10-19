<?php

namespace App\Modules\Accommodation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccommodationSeasonPositionRequest extends FormRequest
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
            'season_unit_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer'],
            'position_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'tenant_id' => ['prohibited'],
            'season_id' => ['prohibited'],
            'unit_id' => ['prohibited'],
        ];
    }
}
