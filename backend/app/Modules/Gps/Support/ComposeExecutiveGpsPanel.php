<?php

namespace App\Modules\Gps\Support;

use App\Models\User;
use App\Modules\Gps\Enums\GpsFreshnessClass;
use App\Modules\Gps\Enums\GpsMarkerStatus;
use App\Modules\Gps\Models\GpsDeviceBinding;
use App\Modules\Gps\Models\GpsProvider;
use App\Modules\Gps\Models\GpsVehicleLastKnown;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use Illuminate\Support\Carbon;

/**
 * G3 — executive dashboard GPS markers from last-known for the season fleet.
 * Simulated fixes are labelled explicitly and never presented as live ops truth.
 */
final class ComposeExecutiveGpsPanel
{
    /**
     * @return array{
     *   freshness_class: string,
     *   markers: list<array{
     *     transport_season_vehicle_id: int,
     *     lat: float,
     *     lng: float,
     *     freshness_class: string,
     *     marker_status: string,
     *     recorded_at: string,
     *     speed_kph: float|null,
     *     heading_deg: float|null,
     *     provider_code: string|null,
     *     source: string|null,
     *     is_simulated: bool,
     *     vehicle_label: string|null
     *   }>
     * }
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

        /** @var list<TransportSeasonVehicle> $fleet */
        $fleet = TransportSeasonVehicle::query()
            ->where('season_id', $season->id)
            ->where('is_active', true)
            ->with('vehicle')
            ->orderBy('id')
            ->get()
            ->all();

        if ($fleet === []) {
            return [
                'freshness_class' => GpsFreshnessClass::Unavailable->value,
                'markers' => [],
            ];
        }

        $fleetIds = array_map(static fn (TransportSeasonVehicle $v): int => (int) $v->id, $fleet);

        /** @var array<int, GpsVehicleLastKnown> $lastKnownByVehicle */
        $lastKnownByVehicle = GpsVehicleLastKnown::query()
            ->where('season_id', $season->id)
            ->whereIn('transport_season_vehicle_id', $fleetIds)
            ->get()
            ->keyBy('transport_season_vehicle_id')
            ->all();

        /** @var array<int, GpsDeviceBinding> $bindingsByVehicle */
        $bindingsByVehicle = GpsDeviceBinding::query()
            ->whereIn('transport_season_vehicle_id', $fleetIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('transport_season_vehicle_id')
            ->all();

        $providerIds = [];
        foreach ($bindingsByVehicle as $binding) {
            if ($binding->gps_provider_id !== null) {
                $providerIds[] = (int) $binding->gps_provider_id;
            }
        }
        /** @var array<int, GpsProvider> $providers */
        $providers = $providerIds === []
            ? []
            : GpsProvider::query()->whereIn('id', array_unique($providerIds))->get()->keyBy('id')->all();

        $markers = [];
        $hasReal = false;
        $hasStale = false;

        foreach ($fleet as $vehicle) {
            $tsvId = (int) $vehicle->id;
            $binding = $bindingsByVehicle[$tsvId] ?? null;
            $row = $lastKnownByVehicle[$tsvId] ?? null;
            $label = $this->vehicleLabel($vehicle);

            if ($row === null) {
                $status = $binding === null
                    ? GpsMarkerStatus::NoDevice
                    : $this->providerUnavailableOrOffline($binding, $providers);

                $markers[] = [
                    'transport_season_vehicle_id' => $tsvId,
                    'lat' => null,
                    'lng' => null,
                    'freshness_class' => GpsFreshnessClass::Unavailable->value,
                    'marker_status' => $status->value,
                    'recorded_at' => null,
                    'speed_kph' => null,
                    'heading_deg' => null,
                    'provider_code' => $binding?->gps_provider_id !== null
                        ? ($providers[(int) $binding->gps_provider_id]->code ?? null)
                        : null,
                    'source' => null,
                    'is_simulated' => false,
                    'vehicle_label' => $label,
                ];

                continue;
            }

            $isSimulated = (bool) $row->is_simulated;
            $freshness = $isSimulated
                ? GpsFreshnessClass::Unavailable
                : GpsFreshness::classify($row->recorded_at, $now);

            if (! $isSimulated && $freshness === GpsFreshnessClass::Real) {
                $hasReal = true;
            } elseif (! $isSimulated && $freshness === GpsFreshnessClass::Stale) {
                $hasStale = true;
            }

            $markerStatus = $isSimulated
                ? GpsMarkerStatus::Simulated
                : ($freshness === GpsFreshnessClass::Real
                    ? GpsMarkerStatus::Online
                    : GpsMarkerStatus::Stale);

            $markers[] = [
                'transport_season_vehicle_id' => $tsvId,
                'lat' => (float) $row->latitude,
                'lng' => (float) $row->longitude,
                // Keep freshness enum G2-compatible; DEMO is signalled via marker_status + is_simulated.
                'freshness_class' => $isSimulated ? GpsFreshnessClass::Unavailable->value : $freshness->value,
                'marker_status' => $markerStatus->value,
                'recorded_at' => $row->recorded_at->toIso8601String(),
                'speed_kph' => $row->speed_kph !== null ? (float) $row->speed_kph : null,
                'heading_deg' => $row->heading_deg !== null ? (float) $row->heading_deg : null,
                'provider_code' => $row->provider_code,
                'source' => $row->source,
                'is_simulated' => $isSimulated,
                'vehicle_label' => $label,
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

    /**
     * @param  array<int, GpsProvider>  $providers
     */
    private function providerUnavailableOrOffline(GpsDeviceBinding $binding, array $providers): GpsMarkerStatus
    {
        if ($binding->gps_provider_id === null) {
            return GpsMarkerStatus::Offline;
        }

        $provider = $providers[(int) $binding->gps_provider_id] ?? null;
        if ($provider === null || ! $provider->is_enabled) {
            return GpsMarkerStatus::ProviderUnavailable;
        }

        return GpsMarkerStatus::Offline;
    }

    private function vehicleLabel(TransportSeasonVehicle $vehicle): ?string
    {
        $plate = $vehicle->vehicle->plate_number ?? null;
        if (is_string($plate) && $plate !== '') {
            return $plate;
        }

        return 'مركبة #'.$vehicle->id;
    }
}
