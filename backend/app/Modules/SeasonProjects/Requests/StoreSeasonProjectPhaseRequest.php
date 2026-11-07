<?php

namespace App\Modules\SeasonProjects\Requests;

use App\Modules\SeasonProjects\Enums\SeasonProjectPhaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeasonProjectPhaseRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['nullable', 'string', Rule::enum(SeasonProjectPhaseStatus::class)],
        ];
    }
}
