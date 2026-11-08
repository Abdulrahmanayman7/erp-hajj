<?php

namespace App\Modules\OperationalKpis\Calculators;

use App\Modules\OperationalKpis\Support\KpiDto;
use App\Modules\OperationalKpis\Support\KpiPrecision;
use App\Modules\OperationalKpis\Support\OperationalKpiCatalog;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Models\SeasonProject;
use Carbon\CarbonInterface;

/**
 * J01–J03, J07, J09 over non-soft-deleted season projects (SoftDeletes scope).
 *
 * J07 = AVG(progress_percent) over non-cancelled projects, NULL progress
 * excluded, empty sample → undefined (never 0).
 * J09 = completed / (active + completed) × 100, zero denominator → undefined.
 */
class SeasonProjectsKpiCalculator implements KpiCalculator
{
    public function group(): string
    {
        return OperationalKpiCatalog::GROUP_SEASON_PROJECTS;
    }

    public function calculate(Season $season, CarbonInterface $now, array $ids): array
    {
        $cancelled = SeasonProjectStatus::Cancelled->value;
        $active = SeasonProjectStatus::Active->value;
        $completed = SeasonProjectStatus::Completed->value;

        // AVG ignores NULLs, so non-matching rows (CASE → NULL) and NULL progress drop out.
        $row = SeasonProject::query()
            ->where('season_id', $season->id)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status <> ? THEN 1 ELSE 0 END), 0) AS j01, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS j02, '
                .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS j03, '
                .'AVG(CASE WHEN status <> ? THEN progress_percent ELSE NULL END) AS j07',
                [$cancelled, $active, $completed, $cancelled],
            )
            ->toBase()
            ->first();

        $activeCount = (int) $row->j02;
        $completedCount = (int) $row->j03;

        $results = [];

        if (in_array('J01', $ids, true)) {
            $results[] = KpiDto::count('J01', (int) $row->j01);
        }
        if (in_array('J02', $ids, true)) {
            $results[] = KpiDto::count('J02', $activeCount);
        }
        if (in_array('J03', $ids, true)) {
            $results[] = KpiDto::count('J03', $completedCount);
        }
        if (in_array('J07', $ids, true)) {
            $results[] = $row->j07 === null
                ? KpiDto::undefined('J07')
                : KpiDto::percent('J07', KpiPrecision::toDecimalString($row->j07));
        }
        if (in_array('J09', $ids, true)) {
            $denominator = $activeCount + $completedCount;
            $results[] = $denominator === 0
                ? KpiDto::undefined('J09')
                : KpiDto::percent('J09', bcdiv(bcmul((string) $completedCount, '100', 8), (string) $denominator, 8));
        }

        return $results;
    }
}
