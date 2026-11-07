<?php

namespace App\Modules\SeasonalTemplates\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSeasonTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Entity kinds are deliberately not validated with `in:` — a non-approved kind
     * must surface as SEASON_COPY_KIND_DENIED from the allowlist, not a generic 422.
     *
     * Snapshot rows are validated field-by-field against the allowlist by
     * `SnapshotNormalizer` (a forbidden field is rejected, never trimmed).
     * `capture_season_id` is resolved through the tenant scope (404 across tenants).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'capture_season_id' => ['nullable', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*' => ['array'],
            'items.*.entity_kind' => ['required', 'string', 'max:64'],
            'items.*.selection' => ['nullable', 'array'],
            'items.*.selection.codes' => ['nullable', 'array', 'max:500'],
            'items.*.selection.codes.*' => ['string', 'max:255'],
            'items.*.snapshot' => ['nullable', 'array'],
        ];
    }
}
