<?php

namespace App\Modules\Gps\Providers\Adapters;

use App\Modules\Gps\Contracts\GpsProviderAdapterInterface;
use App\Modules\Gps\Contracts\NormalizedLocationDto;
use App\Modules\Gps\Enums\GpsConnectionMethod;
use App\Modules\Gps\Enums\GpsProviderHealthStatus;
use App\Modules\Gps\Models\GpsDeviceBinding;
use App\Modules\Gps\Models\GpsProvider;
use App\Modules\Gps\Support\GpsConnectionTestResult;
use App\Modules\Gps\Support\GpsOutboundGate;
use Illuminate\Support\Carbon;

/**
 * Synthetic DEMO GPS provider. Never allowed in production. Never claims real ops locations.
 */
final class SimulatorGpsAdapter implements GpsProviderAdapterInterface
{
    public function __construct(
        private readonly GpsOutboundGate $gate,
    ) {}

    public function connectionMethod(): string
    {
        return GpsConnectionMethod::Simulator->value;
    }

    public function testConnection(GpsProvider $provider): GpsConnectionTestResult
    {
        try {
            $this->gate->assertCanRunSimulator();
        } catch (\Throwable $e) {
            return new GpsConnectionTestResult(
                GpsProviderHealthStatus::Blocked,
                $e->getMessage(),
            );
        }

        return new GpsConnectionTestResult(
            GpsProviderHealthStatus::Healthy,
            'DEMO simulator ready (synthetic data only).',
            false,
        );
    }

    public function fetchLatestPositions(GpsProvider $provider): array
    {
        $this->gate->assertCanRunSimulator();

        $settings = $provider->settings();
        $simulateFailure = (bool) ($settings['simulate_failure'] ?? false);
        if ($simulateFailure) {
            throw new \RuntimeException('Simulated provider failure.');
        }

        $offlineDeviceIds = array_map('strval', $settings['offline_device_ids'] ?? []);
        $baseLat = (float) ($settings['base_latitude'] ?? 21.4225);
        $baseLng = (float) ($settings['base_longitude'] ?? 39.8262);
        $radiusKm = max(0.1, (float) ($settings['radius_km'] ?? 3.0));

        /** @var list<GpsDeviceBinding> $bindings */
        $bindings = GpsDeviceBinding::query()
            ->where('gps_provider_id', $provider->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->all();

        $now = Carbon::now('UTC');
        $out = [];
        $i = 0;

        foreach ($bindings as $binding) {
            $deviceKey = (string) ($binding->external_device_id ?: $binding->device_id);
            if (in_array($deviceKey, $offlineDeviceIds, true)) {
                $i++;

                continue;
            }

            $angle = deg2rad(($i * 47) + ((int) $now->timestamp % 360));
            $distanceDeg = ($radiusKm / 111.0) * (0.4 + (($i % 5) * 0.12));
            $lat = $baseLat + ($distanceDeg * cos($angle));
            $lng = $baseLng + ($distanceDeg * sin($angle) / max(0.2, cos(deg2rad($baseLat))));
            $speed = 20 + ($i % 8) * 5;
            $heading = fmod(($i * 35) + ($now->second * 2), 360);

            $out[] = new NormalizedLocationDto(
                transportSeasonVehicleId: (int) $binding->transport_season_vehicle_id,
                latitude: round($lat, 7),
                longitude: round($lng, 7),
                recordedAt: $now->copy(),
                deviceId: (string) $binding->device_id,
                providerCode: (string) $provider->code,
                externalDeviceId: $binding->external_device_id,
                speedKph: (float) $speed,
                headingDeg: (float) $heading,
                accuracyM: 8.0,
                receivedAt: $now->copy(),
                source: 'simulator',
                isSimulated: true,
                gpsProviderId: (int) $provider->id,
            );
            $i++;
        }

        return $out;
    }
}
