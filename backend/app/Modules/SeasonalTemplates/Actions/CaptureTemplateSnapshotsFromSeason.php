<?php

namespace App\Modules\SeasonalTemplates\Actions;

use App\Core\Tenancy\TenantContext;
use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;
use App\Modules\SeasonalTemplates\Support\SafeCopyAdapterRegistry;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Models\Season;

/**
 * Populates template item snapshots from a live season (X01).
 *
 * Rows are read only through the allowlisted adapters (never raw tables), so a
 * snapshot can only ever hold approved fields. The season is resolved through the
 * tenant scope: another tenant's season id is indistinguishable from unknown (404).
 * Items that already carry an explicit snapshot keep it.
 */
final class CaptureTemplateSnapshotsFromSeason
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SafeCopyAdapterRegistry $adapters,
    ) {}

    /**
     * @param  list<array{entity_kind: string, selection: array<string, mixed>|null, snapshot: array<string, mixed>|null}>  $items
     * @return list<array{entity_kind: string, selection: array<string, mixed>|null, snapshot: array<string, mixed>|null}>
     */
    public function execute(array $items, int $seasonId): array
    {
        $this->tenantContext->require();

        $season = Season::query()->findOrFail($seasonId);
        if (! in_array($season->status, [SeasonStatus::Draft, SeasonStatus::Active, SeasonStatus::Closed], true)) {
            throw SeasonalTemplatesDomainException::sourceInvalid();
        }

        $anchor = $season->operational_starts_on->toDateString();

        foreach ($items as $index => $item) {
            $kind = $item['entity_kind'];
            if ($item['snapshot'] !== null || SafeCopyAllowlist::isSnapshotOnly($kind)) {
                continue;
            }

            $rows = $this->adapters->get($kind)->capture($season);

            $codes = (array) ($item['selection']['codes'] ?? []);
            if ($codes !== [] && ! in_array('*', $codes, true)) {
                $rows = array_filter(
                    $rows,
                    static fn (int|string $key): bool => in_array((string) $key, $codes, true),
                    ARRAY_FILTER_USE_KEY,
                );
            }

            if ($rows === []) {
                continue;
            }

            ksort($rows);
            $items[$index]['snapshot'] = ['anchor' => $anchor, 'rows' => $rows];
        }

        return $items;
    }
}
