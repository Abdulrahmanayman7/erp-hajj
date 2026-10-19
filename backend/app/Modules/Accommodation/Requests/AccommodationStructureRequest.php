<?php

namespace App\Modules\Accommodation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccommodationStructureRequest extends FormRequest
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
            'kind' => [$required, 'string', Rule::in(['building', 'floor'])],
            'name' => [$required, 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer'],
            'tenant_id' => ['prohibited'],
            'season_id' => ['prohibited'],
            'facility_id' => ['prohibited'],
        ];
    }
}
