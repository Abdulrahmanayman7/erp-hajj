<?php

namespace App\Modules\SeasonalTemplates\Support;

use Illuminate\Support\Carbon;

/**
 * `season_start_delta` date strategy (X05): shift an approved planning date by the
 * number of days between the source anchor and the target anchor
 * (`operational_starts_on`). Execution timestamps (`*_at`) are never remapped.
 */
final class DateRemap
{
    public const REASON_ANCHOR_MISSING = 'DATE_ANCHOR_MISSING';

    public const REASON_RANGE_INVALID = 'DATE_RANGE_INVALID';

    /**
     * @return array{strategy: string, source_anchor: string|null, target_anchor: string, delta_days: int|null}
     */
    public static function mapping(?string $sourceAnchor, string $targetAnchor): array
    {
        $delta = $sourceAnchor === null
            ? null
            : (int) Carbon::parse($sourceAnchor)->startOfDay()->diffInDays(Carbon::parse($targetAnchor)->startOfDay(), false);

        return [
            'strategy' => SafeCopyOrchestrator::DATE_STRATEGY,
            'source_anchor' => $sourceAnchor,
            'target_anchor' => $targetAnchor,
            'delta_days' => $delta,
        ];
    }

    /**
     * Remap the listed date fields of one row.
     *
     * A field holding a date is excluded (set to null and reported in `missing`)
     * when the mapping has no source anchor (DATE_ANCHOR_MISSING).
     *
     * @param  array<string, mixed>  $fields
     * @param  array{strategy: string, source_anchor: string|null, target_anchor: string, delta_days: int|null}  $mapping
     * @param  list<string>  $dateFields
     * @return array{fields: array<string, mixed>, transforms: array<string, array{before: string, after: string}>, missing: list<string>}
     */
    public static function transformDates(array $fields, array $mapping, array $dateFields = []): array
    {
        $transforms = [];
        $missing = [];

        foreach ($dateFields as $field) {
            $before = $fields[$field] ?? null;
            if ($before === null || $before === '') {
                $fields[$field] = null;

                continue;
            }

            if ($mapping['delta_days'] === null) {
                $fields[$field] = null;
                $missing[] = $field;

                continue;
            }

            $after = Carbon::parse((string) $before)->startOfDay()->addDays($mapping['delta_days'])->toDateString();
            $fields[$field] = $after;
            $transforms[$field] = ['before' => (string) $before, 'after' => $after];
        }

        return ['fields' => $fields, 'transforms' => $transforms, 'missing' => $missing];
    }
}
