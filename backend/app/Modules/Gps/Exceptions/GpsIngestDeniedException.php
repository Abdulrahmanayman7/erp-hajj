<?php

namespace App\Modules\Gps\Exceptions;

use App\Core\Shared\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Uniform GPS ingest denial. Client-facing body must not distinguish
 * unknown tenant / missing secret / bad signature / unbound device.
 */
final class GpsIngestDeniedException extends RuntimeException
{
    public const ERROR_CODE = 'GPS_INGEST_DENIED';

    public const CLIENT_MESSAGE = 'Invalid device.';

    public function __construct()
    {
        parent::__construct(self::CLIENT_MESSAGE);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(
            message: self::CLIENT_MESSAGE,
            code: self::ERROR_CODE,
            status: 403,
        );
    }
}
