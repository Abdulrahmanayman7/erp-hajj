<?php

namespace App\Modules\Gps\Controllers;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\ApiResponse;
use App\Modules\Gps\Models\GpsDeviceBinding;
use App\Modules\Gps\Models\GpsProvider;
use App\Modules\Gps\Requests\StoreGpsDeviceBindingRequest;
use App\Modules\Gps\Requests\UpdateGpsDeviceBindingRequest;
use App\Modules\Gps\Resources\GpsDeviceBindingResource;
use App\Modules\Transportation\Models\TransportSeasonVehicle;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class GpsDeviceBindingController
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GpsDeviceBinding::class);
        $query = GpsDeviceBinding::query()
            ->with(['provider', 'seasonVehicle.vehicle', 'lastKnown'])
            ->orderBy('id');

        if ($request->filled('gps_provider_id')) {
            $query->where('gps_provider_id', (int) $request->query('gps_provider_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            if ($search !== '') {
                $like = '%'.$search.'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('device_id', 'like', $like)
                        ->orWhere('external_device_id', 'like', $like)
                        ->orWhereHas('provider', function ($providerQuery) use ($like): void {
                            $providerQuery->where('code', 'like', $like)->orWhere('name', 'like', $like);
                        })
                        ->orWhereHas('seasonVehicle.vehicle', function ($vehicleQuery) use ($like): void {
                            $vehicleQuery->where('plate_number', 'like', $like)
                                ->orWhere('vehicle_number', 'like', $like);
                        });
                });
            }
        }

        return ApiResponse::success(data: GpsDeviceBindingResource::collection($query->get()));
    }

    public function store(
        StoreGpsDeviceBindingRequest $request,
        AuthorizationSecurity $security,
    ): JsonResponse {
        $this->authorize('create', GpsDeviceBinding::class);
        $data = $request->validated();
        $deviceId = trim((string) $data['device_id']);
        $tsvId = (int) $data['transport_season_vehicle_id'];
        $providerId = isset($data['gps_provider_id']) ? (int) $data['gps_provider_id'] : null;

        $vehicle = TransportSeasonVehicle::query()->whereKey($tsvId)->first();
        if ($vehicle === null) {
            throw ValidationException::withMessages([
                'transport_season_vehicle_id' => ['المركبة غير موجودة في الموسم.'],
            ]);
        }

        if ($providerId !== null && ! GpsProvider::query()->whereKey($providerId)->exists()) {
            throw ValidationException::withMessages([
                'gps_provider_id' => ['مزود GPS غير موجود.'],
            ]);
        }

        if (GpsDeviceBinding::query()->where('device_id', $deviceId)->exists()) {
            throw ValidationException::withMessages([
                'device_id' => ['معرف الجهاز مرتبط مسبقاً.'],
            ]);
        }

        if (GpsDeviceBinding::query()->where('transport_season_vehicle_id', $tsvId)->exists()) {
            throw ValidationException::withMessages([
                'transport_season_vehicle_id' => ['المركبة مرتبطة بجهاز GPS آخر.'],
            ]);
        }

        $binding = new GpsDeviceBinding;
        $binding->forceFill([
            'device_id' => $deviceId,
            'external_device_id' => $data['external_device_id'] ?? $deviceId,
            'transport_season_vehicle_id' => $tsvId,
            'gps_provider_id' => $providerId,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $binding->save();

        $security->record(AuthorizationSecurityEvent::GPS_DEVICE_BINDING_UPSERTED, [
            'gps_device_binding_id' => $binding->id,
            'transport_season_vehicle_id' => $tsvId,
            'gps_provider_id' => $providerId,
            'action' => 'created',
        ], $request);

        return ApiResponse::success(
            data: new GpsDeviceBindingResource($binding->fresh()->load(['provider', 'seasonVehicle.vehicle', 'lastKnown'])),
            status: 201,
        );
    }

    public function update(
        UpdateGpsDeviceBindingRequest $request,
        GpsDeviceBinding $gps_device_binding,
        AuthorizationSecurity $security,
    ): JsonResponse {
        $this->authorize('update', $gps_device_binding);
        $data = $request->validated();

        if (isset($data['transport_season_vehicle_id'])) {
            $tsvId = (int) $data['transport_season_vehicle_id'];
            if (! TransportSeasonVehicle::query()->whereKey($tsvId)->exists()) {
                throw ValidationException::withMessages([
                    'transport_season_vehicle_id' => ['المركبة غير موجودة في الموسم.'],
                ]);
            }
            $conflict = GpsDeviceBinding::query()
                ->where('transport_season_vehicle_id', $tsvId)
                ->where('id', '!=', $gps_device_binding->id)
                ->exists();
            if ($conflict) {
                throw ValidationException::withMessages([
                    'transport_season_vehicle_id' => ['المركبة مرتبطة بجهاز GPS آخر.'],
                ]);
            }
            $gps_device_binding->transport_season_vehicle_id = $tsvId;
        }

        if (array_key_exists('gps_provider_id', $data)) {
            $providerId = $data['gps_provider_id'] === null ? null : (int) $data['gps_provider_id'];
            if ($providerId !== null && ! GpsProvider::query()->whereKey($providerId)->exists()) {
                throw ValidationException::withMessages([
                    'gps_provider_id' => ['مزود GPS غير موجود.'],
                ]);
            }
            $gps_device_binding->gps_provider_id = $providerId;
        }

        if (array_key_exists('external_device_id', $data)) {
            $gps_device_binding->external_device_id = $data['external_device_id'];
        }
        if (array_key_exists('is_active', $data)) {
            $gps_device_binding->is_active = (bool) $data['is_active'];
        }
        $gps_device_binding->save();

        $security->record(AuthorizationSecurityEvent::GPS_DEVICE_BINDING_UPSERTED, [
            'gps_device_binding_id' => $gps_device_binding->id,
            'transport_season_vehicle_id' => $gps_device_binding->transport_season_vehicle_id,
            'gps_provider_id' => $gps_device_binding->gps_provider_id,
            'action' => 'updated',
        ], $request);

        return ApiResponse::success(
            data: new GpsDeviceBindingResource(
                $gps_device_binding->fresh()->load(['provider', 'seasonVehicle.vehicle', 'lastKnown']),
            ),
        );
    }

    public function destroy(
        Request $request,
        GpsDeviceBinding $gps_device_binding,
        AuthorizationSecurity $security,
    ): JsonResponse {
        $this->authorize('delete', $gps_device_binding);
        $id = $gps_device_binding->id;
        $gps_device_binding->delete();

        $security->record(AuthorizationSecurityEvent::GPS_DEVICE_BINDING_DELETED, [
            'gps_device_binding_id' => $id,
        ], $request);

        return ApiResponse::success(data: null);
    }
}
