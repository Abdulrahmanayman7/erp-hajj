<?php

namespace App\Modules\Accommodation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccommodationFacilityScopeRequest extends FormRequest
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
            'user_id' => ['required', 'integer'],
            'facility_id' => ['required', 'integer'],
            'tenant_id' => ['prohibited'],
            'season_id' => ['prohibited'],
        ];
    }
}
