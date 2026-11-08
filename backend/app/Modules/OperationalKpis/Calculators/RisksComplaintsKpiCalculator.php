<?php

namespace App\Modules\OperationalKpis\Calculators;

use App\Modules\OperationalKpis\Support\KpiDto;
use App\Modules\OperationalKpis\Support\OperationalKpiCatalog;
use App\Modules\RisksAndComplaints\Enums\ComplaintStatus;
use App\Modules\RisksAndComplaints\Enums\RiskClosureOutcome;
use App\Modules\RisksAndComplaints\Enums\RiskSeverity;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\SeasonPilgrims\Models\Season;
use Carbon\CarbonInterface;

/**
 * R01–R03 (risks) and R04, R05, R09 (complaints) over non-soft-deleted rows.
 * Risks and complaints are authorized separately (`risks.view` /
 * `complaints.view`); the table that is not requested is never queried.
 */
class RisksComplaintsKpiCalculator implements KpiCalculator
{
    public function group(): string
    {
        return OperationalKpiCatalog::GROUP_RISKS_COMPLAINTS;
    }

    public function calculate(Season $season, CarbonInterface $now, array $ids): array
    {
        $results = [];

        if (array_intersect(['R01', 'R02', 'R03'], $ids) !== []) {
            $closed = RiskStatus::Closed->value;

            // R02: NULL severity_level never matches IN (…) → excluded (pre-assessment).
            $row = OperationalRisk::query()
                ->where('season_id', $season->id)
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN status <> ? THEN 1 ELSE 0 END), 0) AS r01, '
                    .'COALESCE(SUM(CASE WHEN severity_level IN (?, ?) AND status <> ? THEN 1 ELSE 0 END), 0) AS r02, '
                    .'COALESCE(SUM(CASE WHEN status = ? AND closure_outcome = ? THEN 1 ELSE 0 END), 0) AS r03',
                    [
                        $closed,
                        RiskSeverity::High->value, RiskSeverity::Critical->value, $closed,
                        $closed, RiskClosureOutcome::Mitigated->value,
                    ],
                )
                ->toBase()
                ->first();

            foreach (['R01', 'R02', 'R03'] as $id) {
                if (in_array($id, $ids, true)) {
                    $results[] = KpiDto::count($id, (int) $row->{strtolower($id)});
                }
            }
        }

        if (array_intersect(['R04', 'R05', 'R09'], $ids) !== []) {
            // R04 and R05 are disjoint: open set excludes resolved/closed; R05 is `resolved` only.
            $row = OperationalComplaint::query()
                ->where('season_id', $season->id)
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END), 0) AS r04, '
                    .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS r05, '
                    .'COALESCE(SUM(CASE WHEN escalation_level > 0 THEN 1 ELSE 0 END), 0) AS r09',
                    [
                        ComplaintStatus::New->value, ComplaintStatus::UnderReview->value, ComplaintStatus::InProgress->value,
                        ComplaintStatus::Resolved->value,
                    ],
                )
                ->toBase()
                ->first();

            foreach (['R04', 'R05', 'R09'] as $id) {
                if (in_array($id, $ids, true)) {
                    $results[] = KpiDto::count($id, (int) $row->{strtolower($id)});
                }
            }
        }

        return $results;
    }
}
