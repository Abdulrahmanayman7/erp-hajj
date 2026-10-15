<?php

namespace App\Modules\SeasonPilgrims\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSeasonRequest extends FormRequest
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
            'code' => ['sometimes', 'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'hajj_year' => ['sometimes', 'required', 'integer', 'between:1400,1600'],
            'operational_starts_on' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'operational_ends_on' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
