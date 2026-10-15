<?php

namespace App\Modules\SeasonPilgrims\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeasonRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'hajj_year' => ['required', 'integer', 'between:1400,1600'],
            'operational_starts_on' => ['required', 'date_format:Y-m-d'],
            'operational_ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:operational_starts_on'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
