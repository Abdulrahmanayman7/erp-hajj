<?php

namespace App\Modules\SeasonalTemplates\Requests;

use App\Modules\SeasonalTemplates\Enums\CopyMode;
use App\Modules\SeasonalTemplates\Support\SafeCopyOrchestrator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Season ids come from the body (these routes run outside `season.context`);
 * tenant_id is never accepted — cross-tenant ids resolve to 404 in the orchestrator.
 */
class CreateSeasonCopyPreviewRequest extends FormRequest
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
            'mode' => ['required', Rule::in(CopyMode::values())],
            // Optional only for from_template: a snapshot-complete template needs no live
            // source (X01). The orchestrator enforces it for kinds without a snapshot.
            'source_season_id' => ['required_unless:mode,'.CopyMode::FromTemplate->value, 'nullable', 'integer', 'min:1'],
            'target_season_id' => ['required', 'integer', 'min:1'],
            'source_template_id' => [
                Rule::requiredIf(fn (): bool => $this->input('mode') === CopyMode::FromTemplate->value),
                'prohibited_if:mode,'.CopyMode::FromSeason->value,
                'nullable',
                'integer',
                'min:1',
            ],
            'selection' => ['nullable', 'array'],
            'selection.entity_kinds' => [
                Rule::requiredIf(fn (): bool => $this->input('mode') === CopyMode::FromSeason->value),
                'array',
                'min:1',
                'max:20',
            ],
            'selection.entity_kinds.*' => ['string', 'max:64'],
            'selection.codes' => ['nullable', 'array'],
            'selection.codes.*' => ['array', 'max:500'],
            'selection.codes.*.*' => ['string', 'max:255'],
            'date_mapping' => ['nullable', 'array'],
            'date_mapping.strategy' => ['nullable', Rule::in([SafeCopyOrchestrator::DATE_STRATEGY])],
        ];
    }
}
