<?php

namespace App\Core\Shared;

use Illuminate\Http\JsonResponse;

/**
 * Standardized API response envelope.
 *
 * Success: { success, message, data, meta? }
 * Error:   { success, message, errors?, code }
 *
 * See docs/04-api/API_STANDARDS.md.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(
        mixed $data = null,
        string $message = '',
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  list<array<string, mixed>>  $reasons  Structured machine reasons (e.g. eligibility failures).
     */
    public static function error(
        string $message,
        string $code,
        array $errors = [],
        int $status = 400,
        array $reasons = [],
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'code' => $code,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        if ($reasons !== []) {
            $payload['reasons'] = $reasons;
        }

        return response()->json($payload, $status);
    }
}
