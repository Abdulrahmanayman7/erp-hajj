<?php

namespace App\Modules\RisksAndComplaints\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRiskRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'season_pilgrim_id' => ['sometimes', 'nullable', 'integer'],
            'group_id' => ['sometimes', 'nullable', 'integer'],
            'facility_id' => ['sometimes', 'nullable', 'integer'],
            'transport_season_vehicle_id' => ['sometimes', 'nullable', 'integer'],
            'project_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
