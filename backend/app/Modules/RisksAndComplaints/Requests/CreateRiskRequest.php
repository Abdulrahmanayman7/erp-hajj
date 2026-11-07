<?php

namespace App\Modules\RisksAndComplaints\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRiskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'likelihood' => ['nullable', 'integer', 'min:1', 'max:5'],
            'impact' => ['nullable', 'integer', 'min:1', 'max:5'],
            'season_pilgrim_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'integer'],
            'facility_id' => ['nullable', 'integer'],
            'transport_season_vehicle_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
        ];
    }
}
