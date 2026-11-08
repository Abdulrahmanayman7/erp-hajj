<?php

namespace App\Modules\SeasonalTemplates\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExecuteSeasonCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `preview_token` is nullable here so a missing token surfaces as
     * SEASON_COPY_PREVIEW_REQUIRED (preview is mandatory before execution).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preview_token' => ['nullable', 'uuid'],
            'content_hash' => ['nullable', 'string', 'size:64'],
            'resolutions' => ['nullable', 'array', 'max:2000'],
            'resolutions.*' => ['array'],
            'resolutions.*.entity_kind' => ['required', 'string', 'max:64'],
            'resolutions.*.natural_key' => ['required', 'string', 'max:255'],
            'resolutions.*.decision' => ['required', Rule::in(['skip', 'overwrite'])],
        ];
    }
}
