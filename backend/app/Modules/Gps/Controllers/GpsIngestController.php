<?php

namespace App\Modules\Gps\Controllers;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\ApiResponse;
use App\Modules\Gps\Support\GpsIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class GpsIngestController
{
    public function store(
        Request $request,
        GpsIngestService $ingest,
        AuthorizationSecurity $security,
    ): JsonResponse {
        $raw = $request->getContent();
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            throw new UnprocessableEntityHttpException('Invalid JSON body.');
        }

        $validator = Validator::make($data, [
            'tenant_id' => ['required', 'integer', 'min:1'],
            'transport_season_vehicle_id' => ['required', 'integer', 'min:1'],
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
            'recorded_at' => ['required', 'date'],
            'nonce' => ['required', 'string', 'min:8', 'max:128'],
            'device_id' => ['required', 'string', 'min:1', 'max:64'],
        ]);

        if ($validator->fails()) {
            throw new UnprocessableEntityHttpException('Invalid ingest payload.');
        }

        $validated = $validator->validated();
        $signature = $request->header('X-Gps-Signature') ?? $request->header('X-Signature');
        $row = $ingest->ingest($raw, $signature, $validated);

        // Security-event audit without coordinates, secrets, or raw payloads.
        $security->record(AuthorizationSecurityEvent::GPS_INGEST_ACCEPTED, [
            'tenant_id' => (int) $validated['tenant_id'],
            'season_id' => (int) $row->season_id,
            'transport_season_vehicle_id' => (int) $row->transport_season_vehicle_id,
            'device_id' => (string) $row->device_id,
        ], $request);

        return ApiResponse::success(data: [
            'transport_season_vehicle_id' => $row->transport_season_vehicle_id,
            'season_id' => $row->season_id,
            'recorded_at' => $row->recorded_at->toIso8601String(),
        ], status: 201);
    }
}
