<?php

namespace App\Modules\SeasonalTemplates\Support;

use App\Modules\SeasonalTemplates\Models\SeasonCopyRowProvenance;

/**
 * Reads/writes {@see SeasonCopyRowProvenance} (tenant-scoped by the model).
 */
final class SafeCopyProvenanceStore
{
    /**
     * @param  list<int>  $targetRowIds
     * @return array<int, SeasonCopyRowProvenance>
     */
    public function forTargets(string $kind, array $targetRowIds): array
    {
        if ($targetRowIds === []) {
            return [];
        }

        return SeasonCopyRowProvenance::query()
            ->where('entity_kind', $kind)
            ->whereIn('target_row_id', $targetRowIds)
            ->get()
            ->keyBy('target_row_id')
            ->all();
    }

    /**
     * Provenance rows of one kind copied into a target season, keyed by copy key.
     * Used by kinds whose target rows are not season-scoped (contract definitions).
     *
     * @return array<string, SeasonCopyRowProvenance>
     */
    public function forTargetSeason(string $kind, int $targetSeasonId): array
    {
        return SeasonCopyRowProvenance::query()
            ->where('entity_kind', $kind)
            ->where('target_season_id', $targetSeasonId)
            ->whereNotNull('natural_key')
            ->orderBy('id')
            ->get()
            ->keyBy('natural_key')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $fields  normalized allowlisted fields
     */
    public static function hashFields(array $fields): string
    {
        return CanonicalJson::hash($fields);
    }

    public function record(
        string $kind,
        int $targetSeasonId,
        int $targetRowId,
        ?int $sourceSeasonId,
        ?int $sourceTemplateId,
        ?int $sourceRowId,
        int $runId,
        string $copiedHash,
        ?string $naturalKey = null,
    ): void {
        SeasonCopyRowProvenance::query()->updateOrCreate(
            ['entity_kind' => $kind, 'target_row_id' => $targetRowId],
            [
                'target_season_id' => $targetSeasonId,
                'natural_key' => $naturalKey,
                'source_season_id' => $sourceSeasonId,
                'source_template_id' => $sourceTemplateId,
                'source_row_id' => $sourceRowId,
                'copy_run_id' => $runId,
                'system_owned' => true,
                'copied_hash' => $copiedHash,
            ],
        );
    }
}
