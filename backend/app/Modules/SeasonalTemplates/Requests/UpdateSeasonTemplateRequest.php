<?php

namespace App\Modules\SeasonalTemplates\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSeasonTemplateRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'items' => ['sometimes', 'required', 'array', 'min:1', 'max:20'],
            'items.*' => ['array'],
            'items.*.entity_kind' => ['required', 'string', 'max:64'],
            'items.*.selection' => ['nullable', 'array'],
            'items.*.selection.codes' => ['nullable', 'array', 'max:500'],
            'items.*.selection.codes.*' => ['string', 'max:255'],
        ];
    }
}
