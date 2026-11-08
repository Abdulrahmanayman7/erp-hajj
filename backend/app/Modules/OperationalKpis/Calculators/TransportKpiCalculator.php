<?php

namespace App\Modules\OperationalKpis\Calculators;

use App\Modules\OperationalKpis\Support\KpiDto;
use App\Modules\OperationalKpis\Support\OperationalKpiCatalog;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\Transportation\Enums\BreakdownStatus;
use App\Modules\Transportation\Enums\MovementStatus;
use App\Modules\Transportation\Models\TransportMovement;
use App\Modules\Transportation\Models\TransportVehicleBreakdown;
use Carbon\CarbonInterface;

/**
 * T01 scheduled · T02 completed · T08 recorded (non-cancelled) breakdowns.
 */
class TransportKpiCalculator implements KpiCalculator
{
    public function group(): string
    {
        return OperationalKpiCatalog::GROUP_TRANSPORT;
    }

    public function calculate(Season $season, CarbonInterface $now, array $ids): array
    {
        $results = [];

        if (array_intersect(['T01', 'T02'], $ids) !== []) {
            $row = TransportMovement::query()
                ->where('season_id', $season->id)
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END), 0) AS t01, '
                    .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS t02',
                    [MovementStatus::Planned->value, MovementStatus::InProgress->value, MovementStatus::Completed->value],
                )
                ->toBase()
                ->first();

            if (in_array('T01', $ids, true)) {
                $results['T01'] = KpiDto::count('T01', (int) $row->t01);
            }
            if (in_array('T02', $ids, true)) {
                $results['T02'] = KpiDto::count('T02', (int) $row->t02);
            }
        }

        if (in_array('T08', $ids, true)) {
            $results['T08'] = KpiDto::count('T08', TransportVehicleBreakdown::query()
                ->where('season_id', $season->id)
                ->whereIn('status', [
                    BreakdownStatus::Open->value,
                    BreakdownStatus::Replaced->value,
                    BreakdownStatus::Closed->value,
                ])
                ->count());
        }

        return array_values($results);
    }
}
