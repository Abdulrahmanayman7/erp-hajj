<?php

namespace App\Modules\RisksAndComplaints\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateComplaintRequest extends FormRequest
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
        $categories = config('risks_complaints.complaint_categories', []);

        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'category_code' => ['required', 'string', Rule::in($categories)],
            'season_pilgrim_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'integer'],
            'facility_id' => ['nullable', 'integer'],
            'transport_season_vehicle_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
        ];
    }
}
