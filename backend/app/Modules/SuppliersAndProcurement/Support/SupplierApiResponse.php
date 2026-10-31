<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SupplierApiResponse
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $warnings
     * @param  array<string, mixed>  $meta
     */
    public static function successWithWarnings(
        array $data,
        array $warnings = [],
        string $message = '',
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'warnings' => $warnings,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function createdWithWarnings(
        array $data,
        array $warnings = [],
        string $message = '',
    ): JsonResponse {
        return self::successWithWarnings($data, $warnings, $message, [], 201);
    }

    public static function paginated(mixed $data, array $meta): JsonResponse
    {
        return ApiResponse::success(data: $data, meta: $meta);
    }
}
