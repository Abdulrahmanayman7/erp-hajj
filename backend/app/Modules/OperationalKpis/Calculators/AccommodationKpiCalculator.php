<?php

namespace App\Modules\OperationalKpis\Calculators;

use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\OperationalKpis\Support\KpiDto;
use App\Modules\OperationalKpis\Support\OperationalKpiCatalog;
use App\Modules\SeasonPilgrims\Models\Season;
use Carbon\CarbonInterface;

/**
 * A02 allocated · A03 actual occupancy — assignment counts whose half-open
 * stay interval [starts_at, effective_ends_at) contains the request snapshot.
 */
class AccommodationKpiCalculator implements KpiCalculator
{
    public function group(): string
    {
        return OperationalKpiCatalog::GROUP_ACCOMMODATION;
    }

    public function calculate(Season $season, CarbonInterface $now, array $ids): array
    {
        // Stored datetimes are in the application timezone; compare as such.
        $instant = $now->copy()->setTimezone((string) config('app.timezone', 'UTC'));

        $row = AccommodationAssignment::query()
            ->where('season_id', $season->id)
            ->whereIn('booking_status', ['confirmed', 'completed'])
            ->where('starts_at', '<=', $instant)
            ->where('effective_ends_at', '>', $instant)
            ->selectRaw(
                'COUNT(*) AS a02, '
                .'COALESCE(SUM(CASE WHEN booking_status = ? AND operational_status IN (?, ?) THEN 1 ELSE 0 END), 0) AS a03',
                ['confirmed', 'arrived', 'checked_in'],
            )
            ->toBase()
            ->first();

        $results = [];

        if (in_array('A02', $ids, true)) {
            $results[] = KpiDto::count('A02', (int) $row->a02);
        }
        if (in_array('A03', $ids, true)) {
            $results[] = KpiDto::count('A03', (int) $row->a03);
        }

        return $results;
    }
}
