<?php

namespace App\Modules\Accommodation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccommodationSupervisorScopeRequest extends FormRequest
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
            'tenant_id' => ['prohibited'],
            'season_id' => ['prohibited'],
            'facility_id' => ['prohibited'],
        ];
    }
}
