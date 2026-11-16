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
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Configurable REST pull adapter with SSRF-safe HTTPS and fail-closed live gate.
 */
final class RestPullGpsAdapter implements GpsProviderAdapterInterface
{
    public function __construct(
        private readonly GpsOutboundGate $gate,
        private readonly GpsSsrfGuard $ssrf,
    ) {}

    public function connectionMethod(): string
    {
        return GpsConnectionMethod::RestPull->value;
    }

    public function testConnection(GpsProvider $provider): GpsConnectionTestResult
    {
        try {
            $this->gate->assertCanLiveOutbound($provider);
            $url = $this->ssrf->assertSafeHttpsUrl((string) ($provider->settings()['endpoint_url'] ?? ''));
        } catch (\Throwable $e) {
            return new GpsConnectionTestResult(
                GpsProviderHealthStatus::Blocked,
                $this->sanitize($e->getMessage()),
            );
        }

        try {
            $response = $this->request($provider, $url);
            if (! $response->successful()) {
                return new GpsConnectionTestResult(
                    GpsProviderHealthStatus::Failed,
                    'REST provider returned HTTP '.$response->status().'.',
                );
            }

            return new GpsConnectionTestResult(
                GpsProviderHealthStatus::Healthy,
                'REST provider responded successfully.',
                true,
            );
        } catch (\Throwable $e) {
            Log::notice('gps.rest_pull.test_failed', ['provider_id' => $provider->id]);

            return new GpsConnectionTestResult(
                GpsProviderHealthStatus::Failed,
                $this->sanitize($e->getMessage()),
            );
        }
    }

    public function fetchLatestPositions(GpsProvider $provider): array
    {
        $this->gate->assertCanLiveOutbound($provider);
        $url = $this->ssrf->assertSafeHttpsUrl((string) ($provider->settings()['endpoint_url'] ?? ''));
        $response = $this->request($provider, $url);
        if (! $response->successful()) {
            throw new \RuntimeException('REST GPS pull failed with HTTP '.$response->status());
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new \RuntimeException('REST GPS pull returned non-JSON body.');
        }

        $itemsPath = (string) ($provider->settings()['items_path'] ?? 'data');
        $items = $this->dig($payload, $itemsPath);
        if (! is_array($items)) {
            $items = array_is_list($payload) ? $payload : [];
        }

        $map = is_array($provider->settings()['field_map'] ?? null)
            ? $provider->settings()['field_map']
            : [];

        $extLat = (string) ($map['latitude'] ?? 'latitude');
        $extLng = (string) ($map['longitude'] ?? 'longitude');
        $extDevice = (string) ($map['device_id'] ?? 'device_id');
        $extRecorded = (string) ($map['recorded_at'] ?? 'recorded_at');
        $extSpeed = (string) ($map['speed_kph'] ?? 'speed_kph');
        $extHeading = (string) ($map['heading_deg'] ?? 'heading_deg');
        $extAccuracy = (string) ($map['accuracy_m'] ?? 'accuracy_m');

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

        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }
            $externalId = trim((string) data_get($row, $extDevice, ''));
            if ($externalId === '' || ! isset($byExternal[$externalId])) {
                continue;
            }
            $binding = $byExternal[$externalId];
            $lat = data_get($row, $extLat);
            $lng = data_get($row, $extLng);
            if (! is_numeric($lat) || ! is_numeric($lng)) {
                continue;
            }

            $recordedRaw = data_get($row, $extRecorded);
            $recordedAt = is_string($recordedRaw) && $recordedRaw !== ''
                ? Carbon::parse($recordedRaw)->utc()
                : $now->copy();

            $out[] = new NormalizedLocationDto(
                transportSeasonVehicleId: (int) $binding->transport_season_vehicle_id,
                latitude: (float) $lat,
                longitude: (float) $lng,
                recordedAt: $recordedAt,
                deviceId: (string) $binding->device_id,
                providerCode: (string) $provider->code,
                externalDeviceId: $externalId,
                speedKph: is_numeric(data_get($row, $extSpeed)) ? (float) data_get($row, $extSpeed) : null,
                headingDeg: is_numeric(data_get($row, $extHeading)) ? (float) data_get($row, $extHeading) : null,
                accuracyM: is_numeric(data_get($row, $extAccuracy)) ? (float) data_get($row, $extAccuracy) : null,
                receivedAt: $now->copy(),
                source: 'rest_pull',
                isSimulated: false,
                gpsProviderId: (int) $provider->id,
            );
        }

        return $out;
    }

    private function request(GpsProvider $provider, string $url): Response
    {
        $creds = $provider->credentialBag();
        $timeout = max(1, min(30, (int) ($provider->settings()['timeout_seconds'] ?? 10)));
        $pending = Http::timeout($timeout)->acceptJson();

        $authType = (string) ($provider->settings()['auth_type'] ?? 'bearer');
        $token = (string) ($creds['api_token'] ?? $creds['bearer_token'] ?? $creds['api_key'] ?? '');

        if ($authType === 'api_key_header' && $token !== '') {
            $header = (string) ($provider->settings()['api_key_header'] ?? 'X-Api-Key');
            $pending = $pending->withHeaders([$header => $token]);
        } elseif ($token !== '') {
            $pending = $pending->withToken($token);
        }

        return $pending->get($url);
    }

    private function dig(array $payload, string $path): mixed
    {
        $path = trim($path);
        if ($path === '' || $path === '.') {
            return $payload;
        }

        return data_get($payload, $path);
    }

    private function sanitize(string $message): string
    {
        return mb_substr(preg_replace('/[^\P{C}\n]+/u', '', $message) ?? $message, 0, 240);
    }
}
