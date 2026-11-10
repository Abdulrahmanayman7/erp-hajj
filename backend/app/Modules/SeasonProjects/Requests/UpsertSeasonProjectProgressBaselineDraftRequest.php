<?php

namespace App\Modules\SeasonProjects\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * S10 U02 — strict, bounded checkpoint payload for draft progress baselines.
 */
class UpsertSeasonProjectProgressBaselineDraftRequest extends FormRequest
{
    public const MAX_CHECKPOINTS = 100;

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
            'checkpoints' => ['required', 'array', 'min:1', 'max:'.self::MAX_CHECKPOINTS],
            'checkpoints.*.effective_at' => ['required', 'date'],
            'checkpoints.*.planned_progress_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var list<array{effective_at?: mixed, planned_progress_percent?: mixed}> $checkpoints */
            $checkpoints = array_values($this->input('checkpoints', []));
            $seen = [];
            $ordered = $checkpoints;
            usort($ordered, static function (array $a, array $b): int {
                return strcmp((string) ($a['effective_at'] ?? ''), (string) ($b['effective_at'] ?? ''));
            });

            $previousPercent = null;
            foreach ($ordered as $row) {
                $effectiveAt = (string) ($row['effective_at'] ?? '');
                if (isset($seen[$effectiveAt])) {
                    $validator->errors()->add('checkpoints', 'Checkpoint effective_at values must be unique.');

                    return;
                }
                $seen[$effectiveAt] = true;

                $percent = (float) ($row['planned_progress_percent'] ?? -1);
                if ($previousPercent !== null && $percent + 0.00001 < $previousPercent) {
                    $validator->errors()->add(
                        'checkpoints',
                        'planned_progress_percent must be non-decreasing when ordered by effective_at.',
                    );

                    return;
                }
                $previousPercent = $percent;
            }
        });
    }
}
