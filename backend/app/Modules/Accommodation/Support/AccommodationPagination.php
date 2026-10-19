<?php

namespace App\Modules\Accommodation\Support;

final class AccommodationPagination
{
    public static function perPage(mixed $value): int
    {
        return max(1, min((int) ($value ?? 15), 100));
    }
}
