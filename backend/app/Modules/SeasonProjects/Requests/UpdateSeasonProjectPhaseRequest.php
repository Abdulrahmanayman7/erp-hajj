<?php

namespace App\Modules\SeasonProjects\Requests;

use App\Modules\SeasonProjects\Enums\SeasonProjectPhaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeasonProjectPhaseRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'starts_on' => ['sometimes', 'nullable', 'date'],
            'ends_on' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'string', Rule::enum(SeasonProjectPhaseStatus::class)],
        ];
    }
}
