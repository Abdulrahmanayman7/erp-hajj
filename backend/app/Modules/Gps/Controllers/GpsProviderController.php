<?php

namespace App\Modules\Gps\Controllers;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\ApiResponse;
use App\Modules\Gps\Enums\GpsConnectionMethod;
use App\Modules\Gps\Enums\GpsProviderHealthStatus;
use App\Modules\Gps\Models\GpsProvider;
use App\Modules\Gps\Requests\StoreGpsProviderRequest;
use App\Modules\Gps\Requests\UpdateGpsProviderRequest;
use App\Modules\Gps\Resources\GpsProviderResource;
use App\Modules\Gps\Support\GpsProviderAdapterRegistry;
use App\Modules\Gps\Support\SyncGpsProvider;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GpsProviderController
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GpsProvider::class);
        $rows = GpsProvider::query()->orderBy('name')->get();

        return ApiResponse::success(data: GpsProviderResource::collection($rows));
    }

    public function store(StoreGpsProviderRequest $request, AuthorizationSecurity $security): JsonResponse
    {
        $this->authorize('create', GpsProvider::class);
        $data = $request->validated();

        if (GpsProvider::query()->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages(['code' => ['رمز المزود مستخدم مسبقاً.']]);
        }

        if (($data['connection_method'] ?? '') === GpsConnectionMethod::Simulator->value
            && app()->environment('production')) {
            throw ValidationException::withMessages([
                'connection_method' => ['محاكي GPS غير مسموح في بيئة الإنتاج.'],
            ]);
        }

        $provider = new GpsProvider;
        $provider->forceFill([
            'name' => $data['name'],
            'code' => $data['code'],
            'connection_method' => $data['connection_method'],
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'poll_interval_seconds' => $data['poll_interval_seconds'] ?? null,
            'settings_json' => $data['settings'] ?? [],
            'last_health_status' => GpsProviderHealthStatus::Unknown->value,
        ]);
        if (isset($data['credentials']) && is_array($data['credentials'])) {
            $filtered = $this->filterCredentials($data['credentials']);
            $provider->credentials_ciphertext = $filtered === [] ? null : $filtered;
        }
        $provider->save();

        $security->record(AuthorizationSecurityEvent::GPS_PROVIDER_UPSERTED, [
            'gps_provider_id' => $provider->id,
            'connection_method' => $provider->connection_method,
            'action' => 'created',
        ], $request);

        return ApiResponse::success(data: new GpsProviderResource($provider->fresh()), status: 201);
    }

    public function show(GpsProvider $gps_provider): JsonResponse
    {
        $this->authorize('view', $gps_provider);

        return ApiResponse::success(data: new GpsProviderResource($gps_provider));
    }

    public function update(
        UpdateGpsProviderRequest $request,
        GpsProvider $gps_provider,
        AuthorizationSecurity $security,
    ): JsonResponse {
        $this->authorize('update', $gps_provider);
        $data = $request->validated();

        if (isset($data['connection_method'])
            && $data['connection_method'] === GpsConnectionMethod::Simulator->value
            && app()->environment('production')) {
            throw ValidationException::withMessages([
                'connection_method' => ['محاكي GPS غير مسموح في بيئة الإنتاج.'],
            ]);
        }

        $fill = [];
        foreach (['name', 'connection_method', 'is_enabled', 'poll_interval_seconds'] as $key) {
            if (array_key_exists($key, $data)) {
                $fill[$key] = $data[$key];
            }
        }
        if (array_key_exists('settings', $data)) {
            $fill['settings_json'] = $data['settings'];
        }
        $gps_provider->forceFill($fill);

        if (array_key_exists('credentials', $data)) {
            if ($data['credentials'] === null) {
                $gps_provider->credentials_ciphertext = null;
            } elseif (is_array($data['credentials'])) {
                $merged = array_merge($gps_provider->credentialBag(), $this->filterCredentials($data['credentials']));
                $gps_provider->credentials_ciphertext = $merged === [] ? null : $merged;
            }
        }
        $gps_provider->save();

        $security->record(AuthorizationSecurityEvent::GPS_PROVIDER_UPSERTED, [
            'gps_provider_id' => $gps_provider->id,
            'connection_method' => $gps_provider->connection_method,
            'action' => 'updated',
        ], $request);

        return ApiResponse::success(data: new GpsProviderResource($gps_provider->fresh()));
    }

    public function destroy(
        Request $request,
        GpsProvider $gps_provider,
        AuthorizationSecurity $security,
    ): JsonResponse {
        $this->authorize('delete', $gps_provider);
        $id = $gps_provider->id;

        DB::transaction(function () use ($gps_provider): void {
            $gps_provider->deviceBindings()->update(['gps_provider_id' => null]);
            $gps_provider->delete();
        });

        $security->record(AuthorizationSecurityEvent::GPS_PROVIDER_DELETED, [
            'gps_provider_id' => $id,
        ], $request);

        return ApiResponse::success(data: null);
    }

    public function test(
        Request $request,
        GpsProvider $gps_provider,
        GpsProviderAdapterRegistry $registry,
    ): JsonResponse {
        $this->authorize('test', $gps_provider);
        $adapter = $registry->resolve($gps_provider->connection_method);
        $result = $adapter->testConnection($gps_provider);

        $gps_provider->forceFill([
            'last_health_status' => $result->status->value,
            'last_error_sanitized' => $result->status === GpsProviderHealthStatus::Healthy
                ? null
                : $result->message,
        ])->save();

        return ApiResponse::success(data: [
            'status' => $result->status->value,
            'message' => $result->message,
            'live_contact_made' => $result->liveContactMade,
            'provider' => new GpsProviderResource($gps_provider->fresh()),
        ]);
    }

    public function sync(
        GpsProvider $gps_provider,
        SyncGpsProvider $sync,
    ): JsonResponse {
        $this->authorize('sync', $gps_provider);
        $result = $sync->sync($gps_provider);

        return ApiResponse::success(data: [
            ...$result,
            'provider' => new GpsProviderResource($gps_provider->fresh()),
        ]);
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    private function filterCredentials(array $credentials): array
    {
        $out = [];
        foreach ($credentials as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                continue;
            }
            $trimmed = trim($value);
            if ($trimmed === '' || $trimmed === '********') {
                continue;
            }
            $out[$key] = $trimmed;
        }

        return $out;
    }
}
