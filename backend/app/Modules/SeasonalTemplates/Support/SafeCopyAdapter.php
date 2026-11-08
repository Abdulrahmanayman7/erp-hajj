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
 *  - excludes:  `reason_code` (e.g. ALREADY_PRESENT_UNCHANGED, DATE_ANCHOR_MISSING)
 *  - conflicts: `conflict_code` (DUPLICATE_KEY | MANUAL_MODIFIED | MISSING_DEPENDENCY | TARGET_LOCKED)
 *
 * Rows come from the template snapshot when the context carries one for the kind,
 * otherwise from the live source season. Planned dates are already remapped.
 */
interface SafeCopyAdapter
{
    public function entityKind(): string;

    /**
     * False for kinds that have no live season source (snapshot-only kinds).
     */
    public function supportsLiveSource(): bool;

    /**
     * Allowlisted rows of a live season keyed by copy key (template snapshot capture).
     *
     * @return array<string, array<string, mixed>>
     */
    public function capture(Season $source): array;

    /**
     * Stable hash of the source (snapshot or live) and target rows relevant to this kind.
     */
    public function fingerprint(SafeCopyContext $context): string;

    /**
     * @return array{adds: list<array<string, mixed>>, changes: list<array<string, mixed>>, excludes: list<array<string, mixed>>, conflicts: list<array<string, mixed>>}
     */
    public function plan(SafeCopyContext $context): array;

    /**
     * @param  array<string, string>  $resolutions  natural_key => 'apply' | 'skip' | 'overwrite'
     *                                              (only keys present are processed)
     * @return list<array{natural_key: string, result: string, reason_code: string|null, source_id: int|null, target_id: int|null, copied_hash: string|null}>
     */
    public function apply(SafeCopyContext $context, array $resolutions, int $runId, User $actor): array;
}
