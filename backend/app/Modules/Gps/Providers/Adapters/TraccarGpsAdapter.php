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
use App\Modules\Gps\Support\GpsSsrfGuard;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Traccar REST adapter against the documented positions/devices API.
 *
 * Does not claim a live Traccar server is connected unless a successful test/sync runs.
 * Live outbound remains gated by GpsOutboundGate (G4).
 */
final class TraccarGpsAdapter implements GpsProviderAdapterInterface
{
    public function __construct(
        private readonly GpsOutboundGate $gate,
        private readonly GpsSsrfGuard $ssrf,
    ) {}

    public function connectionMethod(): string
    {
        return GpsConnectionMethod::Traccar->value;
    }

    public function testConnection(GpsProvider $provider): GpsConnectionTestResult
    {
        try {
            $this->gate->assertCanLiveOutbound($provider);
            $base = $this->baseUrl($provider);
        } catch (\Throwable $e) {
            return new GpsConnectionTestResult(
                GpsProviderHealthStatus::Blocked,
                $this->sanitize($e->getMessage()),
            );
        }

        try {
            $response = $this->client($provider)->get($base.'/api/server');
            if (! $response->successful()) {
                return new GpsConnectionTestResult(
                    GpsProviderHealthStatus::Failed,
                    'Traccar server check returned HTTP '.$response->status().'.',
                );
            }

            return new GpsConnectionTestResult(
                GpsProviderHealthStatus::Healthy,
                'Traccar server responded to /api/server.',
                true,
            );
        } catch (\Throwable $e) {
            Log::notice('gps.traccar.test_failed', ['provider_id' => $provider->id]);

            return new GpsConnectionTestResult(
                GpsProviderHealthStatus::Failed,
                $this->sanitize($e->getMessage()),
            );
        }
    }

    public function fetchLatestPositions(GpsProvider $provider): array
    {
        $this->gate->assertCanLiveOutbound($provider);
        $base = $this->baseUrl($provider);
        $response = $this->client($provider)->get($base.'/api/positions');
        if (! $response->successful()) {
            throw new \RuntimeException('Traccar positions fetch failed with HTTP '.$response->status());
        }

        $positions = $response->json();
        if (! is_array($positions)) {
            throw new \RuntimeException('Traccar positions response was not a JSON array.');
        }

        /** @var array<string, GpsDeviceBinding> $byExternal */
        $byExternal = [];
        foreach (
            GpsDeviceBinding::query()
                ->where('gps_provider_id', $provider->id)
                ->where('is_active', true)
                ->get() as $binding
        ) {
            $key = (string) ($binding->external_device_id ?: $binding->device_id);
            $byExternal[$key] = $binding;
        }

        $now = Carbon::now('UTC');
        $out = [];

        foreach ($positions as $row) {
            if (! is_array($row)) {
                continue;
            }
            $deviceId = (string) ($row['deviceId'] ?? '');
            if ($deviceId === '' || ! isset($byExternal[$deviceId])) {
                continue;
            }
            $binding = $byExternal[$deviceId];
            $lat = $row['latitude'] ?? null;
            $lng = $row['longitude'] ?? null;
            if (! is_numeric($lat) || ! is_numeric($lng)) {
                continue;
            }

            $fix = $row['fix'] ?? null;
            $recordedAt = is_string($fix) && $fix !== ''
                ? Carbon::parse($fix)->utc()
                : $now->copy();

            $speedKnots = is_numeric($row['speed'] ?? null) ? (float) $row['speed'] : null;
            $speedKph = $speedKnots !== null ? $speedKnots * 1.852 : null;
            $course = is_numeric($row['course'] ?? null) ? (float) $row['course'] : null;
            $accuracy = is_numeric($row['accuracy'] ?? null) ? (float) $row['accuracy'] : null;

            $out[] = new NormalizedLocationDto(
                transportSeasonVehicleId: (int) $binding->transport_season_vehicle_id,
                latitude: (float) $lat,
                longitude: (float) $lng,
                recordedAt: $recordedAt,
                deviceId: (string) $binding->device_id,
                providerCode: (string) $provider->code,
                externalDeviceId: $deviceId,
                speedKph: $speedKph,
                headingDeg: $course,
                accuracyM: $accuracy,
                receivedAt: $now->copy(),
                source: 'traccar',
                isSimulated: false,
                gpsProviderId: (int) $provider->id,
            );
        }

        return $out;
    }

    private function baseUrl(GpsProvider $provider): string
    {
        $url = (string) ($provider->settings()['base_url'] ?? '');

        return rtrim($this->ssrf->assertSafeHttpsUrl($url), '/');
    }

    private function client(GpsProvider $provider): PendingRequest
    {
        $creds = $provider->credentialBag();
        $timeout = max(1, min(30, (int) ($provider->settings()['timeout_seconds'] ?? 10)));
        $pending = Http::timeout($timeout)->acceptJson();

        $email = (string) ($creds['email'] ?? $creds['username'] ?? '');
        $password = (string) ($creds['password'] ?? '');
        $token = (string) ($creds['token'] ?? $creds['api_token'] ?? '');

        if ($token !== '') {
            return $pending->withToken($token);
        }

        if ($email !== '' && $password !== '') {
            return $pending->withBasicAuth($email, $password);
        }

        throw new \RuntimeException('Traccar credentials are incomplete.');
    }

    private function sanitize(string $message): string
    {
        return mb_substr(preg_replace('/[^\P{C}\n]+/u', '', $message) ?? $message, 0, 240);
    }
}
