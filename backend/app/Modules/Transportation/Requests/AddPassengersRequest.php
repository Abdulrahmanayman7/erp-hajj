<?php

namespace App\Modules\Transportation\Requests;

use App\Modules\Transportation\Enums\AssignmentSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddPassengersRequest extends FormRequest
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
            'source' => ['required', 'string', Rule::in(AssignmentSource::values())],
            'season_pilgrim_ids' => ['required_if:source,individual', 'prohibited_unless:source,individual', 'array', 'min:1', 'max:500'],
            'season_pilgrim_ids.*' => ['integer', 'distinct'],
            'source_id' => ['required_unless:source,individual', 'prohibited_if:source,individual', 'integer'],
            'entitlement_override' => ['sometimes', 'boolean'],
            'override_reason' => ['required_if_accepted:entitlement_override', 'nullable', 'string', 'min:3', 'max:1000'],
        ];
    }
}
