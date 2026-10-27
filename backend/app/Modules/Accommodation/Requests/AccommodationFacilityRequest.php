<?php

namespace App\Modules\Accommodation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccommodationFacilityRequest extends FormRequest
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
            'kind' => [$required, 'string', Rule::in(['hotel', 'camp'])],
            'code' => ['sometimes', 'string', 'max:40'],
            'name' => [$required, 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'tenant_id' => ['prohibited'],
            'season_id' => ['prohibited'],
        ];
    }
}
