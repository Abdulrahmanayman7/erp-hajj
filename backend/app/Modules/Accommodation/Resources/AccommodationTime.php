<?php

namespace App\Modules\Accommodation\Resources;

use DateTimeInterface;
use Illuminate\Support\Carbon;

final class AccommodationTime
{
    public static function utc(?DateTimeInterface $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::instance(\DateTimeImmutable::createFromInterface($value))
            ->utc()
            ->format('Y-m-d\TH:i:s\Z');
    }
}
