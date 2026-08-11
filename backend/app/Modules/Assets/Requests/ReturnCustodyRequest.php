<?php

namespace App\Modules\Assets\Requests;

use App\Modules\Assets\Enums\AssetCondition;
use App\Modules\Assets\Enums\AssetStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnCustodyRequest extends FormRequest
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
            'next_status' => ['required', 'string', Rule::in(AssetStatus::returnNextStatuses())],
            'condition_at_return' => ['nullable', 'string', Rule::in(AssetCondition::values())],
            'return_notes' => ['nullable', 'string'],
        ];
    }
}
