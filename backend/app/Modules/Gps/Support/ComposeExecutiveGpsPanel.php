<?php

namespace App\Modules\Gps\Support;

use App\Models\User;
use App\Modules\Gps\Enums\GpsFreshnessClass;
use App\Modules\Gps\Models\GpsVehicleLastKnown;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use Illuminate\Support\Carbon;

/**
 * G3 — executive dashboard GPS markers from last-known for the season fleet.
 * Never emits SIMULATED. UNAVAILABLE + empty markers when none exist.
 */
final class ComposeExecutiveGpsPanel
{
    /**
     * @return array{freshness_class: string, markers: list<array{transport_season_vehicle_id: int, lat: float, lng: float, freshness_class: string, recorded_at: string}>}
     */
    public function compose(User $actor, Season $season, ?Carbon $now = null): array
    {
        if (! $actor->hasPermission('gps.map.view')) {
            return [
                'freshness_class' => GpsFreshnessClass::Unavailable->value,
                'markers' => [],
            ];
        }

        $now ??= now();

        $fleetIds = TransportSeasonVehicle::query()
            ->where('season_id', $season->id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if ($fleetIds === []) {
            return [
                'freshness_class' => GpsFreshnessClass::Unavailable->value,
                'markers' => [],
            ];
        }

        /** @var list<GpsVehicleLastKnown> $rows */
        $rows = GpsVehicleLastKnown::query()
            ->where('season_id', $season->id)
            ->whereIn('transport_season_vehicle_id', $fleetIds)
            ->orderBy('transport_season_vehicle_id')
            ->get()
            ->all();

        if ($rows === []) {
            return [
                'freshness_class' => GpsFreshnessClass::Unavailable->value,
                'markers' => [],
            ];
        }

        $markers = [];
        $hasReal = false;
        $hasStale = false;

        foreach ($rows as $row) {
            $freshness = GpsFreshness::classify($row->recorded_at, $now);
            if ($freshness === GpsFreshnessClass::Real) {
                $hasReal = true;
            } elseif ($freshness === GpsFreshnessClass::Stale) {
                $hasStale = true;
            }

            $markers[] = [
                'transport_season_vehicle_id' => (int) $row->transport_season_vehicle_id,
                'lat' => (float) $row->latitude,
                'lng' => (float) $row->longitude,
                'freshness_class' => $freshness->value,
                'recorded_at' => $row->recorded_at->toIso8601String(),
            ];
        }

        $section = $hasReal
            ? GpsFreshnessClass::Real
            : ($hasStale ? GpsFreshnessClass::Stale : GpsFreshnessClass::Unavailable);

        return [
            'freshness_class' => $section->value,
            'markers' => $markers,
        ];
    }
}
