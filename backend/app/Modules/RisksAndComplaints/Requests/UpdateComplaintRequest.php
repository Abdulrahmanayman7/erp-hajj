<?php

namespace App\Modules\RisksAndComplaints\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplaintRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'body' => ['sometimes', 'required', 'string'],
            'category_code' => ['sometimes', 'required', 'string', Rule::in($categories)],
            'start_review' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['under_review'])],
            'season_pilgrim_id' => ['sometimes', 'nullable', 'integer'],
            'group_id' => ['sometimes', 'nullable', 'integer'],
            'facility_id' => ['sometimes', 'nullable', 'integer'],
            'transport_season_vehicle_id' => ['sometimes', 'nullable', 'integer'],
            'project_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
