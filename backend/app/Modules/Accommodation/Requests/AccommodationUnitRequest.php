<?php

namespace App\Modules\Accommodation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccommodationUnitRequest extends FormRequest
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
            'unit_kind' => [$required, 'string', Rule::in(['room', 'tent'])],
            'code' => [$required, 'string', 'max:40'],
            'structure_id' => ['sometimes', 'nullable', 'integer'],
            'tenant_id' => ['prohibited'],
            'season_id' => ['prohibited'],
            'facility_id' => ['prohibited'],
        ];
    }
}
