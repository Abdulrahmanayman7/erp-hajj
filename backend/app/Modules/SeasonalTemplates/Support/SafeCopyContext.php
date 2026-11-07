<?php

namespace App\Modules\SeasonalTemplates\Support;

use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Http\Request;
use LogicException;

/**
 * Everything an adapter needs for one plan/apply pass.
 *
 * A kind with a snapshot (X01) reads its rows from the template snapshot and needs
 * no live source season; a kind without one reads the live source season.
 */
final class SafeCopyContext
{
    /**
     * @param  array{entity_kinds: list<string>, codes: array<string, list<string>>}  $selection
     * @param  array<string, array{anchor: string|null, rows: array<string, array<string, mixed>>}>  $snapshots  by entity kind
     */
    public function __construct(
        public readonly ?Season $source,
        public readonly Season $target,
        public readonly array $selection,
        public readonly array $snapshots,
        public readonly Request $request,
    ) {}

    /**
     * @return array{anchor: string|null, rows: array<string, array<string, mixed>>}|null
     */
    public function snapshotFor(string $kind): ?array
    {
        return $this->snapshots[$kind] ?? null;
    }

    public function liveSource(): Season
    {
        return $this->source ?? throw new LogicException('A live source season is required for a kind without a snapshot.');
    }

    /**
     * @return array{strategy: string, source_anchor: string|null, target_anchor: string, delta_days: int|null}
     */
    public function mappingFor(string $kind): array
    {
        $snapshot = $this->snapshotFor($kind);
        $sourceAnchor = $snapshot !== null
            ? $snapshot['anchor']
            : $this->source?->operational_starts_on->toDateString();

        return DateRemap::mapping($sourceAnchor, $this->target->operational_starts_on->toDateString());
    }
}
