<?php

namespace App\Modules\SeasonalTemplates\Support;

use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Season;

/**
 * Domain adapter for one approved entity kind. Adapters carry only the
 * allowlisted fields of {@see SafeCopyAllowlist} and never create shared masters.
 *
 * Plan lines (all arrays carry `natural_key`, `source_id`, `summary`):
 *  - adds:      row will be created
 *  - changes:   `target_id`, system-owned unmodified row will be updated (summary.diff)
 *  - excludes:  `reason_code` (e.g. ALREADY_PRESENT_UNCHANGED)
 *  - conflicts: `conflict_code` (DUPLICATE_KEY | MANUAL_MODIFIED | MISSING_DEPENDENCY)
 */
interface SafeCopyAdapter
{
    public function entityKind(): string;

    /**
     * Stable hash of the source and target rows relevant to this kind.
     */
    public function fingerprint(Season $source, Season $target): string;

    /**
     * @param  array{entity_kinds: list<string>, codes: array<string, list<string>>}  $selection
     * @return array{adds: list<array<string, mixed>>, changes: list<array<string, mixed>>, excludes: list<array<string, mixed>>, conflicts: list<array<string, mixed>>}
     */
    public function plan(Season $source, Season $target, array $selection): array;

    /**
     * @param  array<string, string>  $resolutions  natural_key => 'apply' | 'skip' | 'overwrite'
     *                                              (only keys present are processed)
     * @return list<array{natural_key: string, result: string, reason_code: string|null, source_id: int|null, target_id: int|null, copied_hash: string|null}>
     */
    public function apply(Season $source, Season $target, array $resolutions, int $runId, User $actor): array;
}
