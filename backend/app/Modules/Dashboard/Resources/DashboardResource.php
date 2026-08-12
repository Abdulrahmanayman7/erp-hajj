<?php

namespace App\Modules\Dashboard\Resources;

/**
 * Thin pass-through for the aggregated dashboard payload.
 */
final class DashboardResource
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function make(array $payload): array
    {
        return $payload;
    }
}
