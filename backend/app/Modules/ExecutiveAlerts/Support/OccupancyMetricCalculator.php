<?php

namespace App\Modules\ExecutiveAlerts\Support;

use App\Modules\Accommodation\Models\AccommodationAssignment;
use App\Modules\Accommodation\Models\AccommodationSeasonUnit;
use App\Modules\Accommodation\Support\AccommodationCapacityGuard;
use App\Modules\SeasonPilgrims\Models\Season;
use Carbon\CarbonInterface;

/**
 * U01 occupancy %: A03 / Σ effectiveCapacity (never A02).
 *
 * @phpstan-type OccupancyMetric array{
 *     state: 'available'|'unavailable',
 *     unavailable_reason: string|null,
 *     a03: int,
 *     denominator: int|null,
 *     occupancy_percent_raw: float|null,
 *     occupancy_percent: float|null
 * }
 */
final class OccupancyMetricCalculator
{
    public const OPEN_THRESHOLD = 90.0;

    public const CLEAR_THRESHOLD = 85.0;

    public function __construct(
        private readonly AccommodationCapacityGuard $capacityGuard,
    ) {}

    /**
     * @return OccupancyMetric
     */
    public function calculate(Season $season, CarbonInterface $now): array
    {
        $instant = $now->copy()->setTimezone((string) config('app.timezone', 'UTC'));

        $a03 = (int) AccommodationAssignment::query()
            ->where('season_id', $season->id)
            ->where('booking_status', 'confirmed')
            ->whereIn('operational_status', ['arrived', 'checked_in'])
            ->where('starts_at', '<=', $instant)
            ->where('effective_ends_at', '>', $instant)
            ->count();

        $units = AccommodationSeasonUnit::query()
            ->where('season_id', $season->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($units->isEmpty()) {
            return $this->unavailable($a03, 'no_active_units');
        }

        $denominator = 0;
        foreach ($units as $unit) {
            $cap = $this->capacityGuard->effectiveCapacity($unit);
            if ($cap === null) {
                return $this->unavailable($a03, 'null_capacity');
            }
            $denominator += max(0, (int) $cap);
        }

        if ($denominator <= 0) {
            return $this->unavailable($a03, 'zero_denominator');
        }

        $raw = (100.0 * $a03) / $denominator;

        return [
            'state' => 'available',
            'unavailable_reason' => null,
            'a03' => $a03,
            'denominator' => $denominator,
            'occupancy_percent_raw' => $raw,
            'occupancy_percent' => round($raw, 1),
        ];
    }

    /**
     * @return OccupancyMetric
     */
    private function unavailable(int $a03, string $reason): array
    {
        return [
            'state' => 'unavailable',
            'unavailable_reason' => $reason,
            'a03' => $a03,
            'denominator' => null,
            'occupancy_percent_raw' => null,
            'occupancy_percent' => null,
        ];
    }
}
