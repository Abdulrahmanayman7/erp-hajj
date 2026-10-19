<?php

namespace App\Modules\Accommodation\Support;

use App\Modules\Accommodation\Exceptions\AccommodationDomainException;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

final class AccommodationPersistence
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function save(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (UniqueConstraintViolationException) {
            throw AccommodationDomainException::codeTaken();
        } catch (QueryException $exception) {
            $message = $exception->getMessage();

            if (str_contains($message, '1062') || str_contains($message, 'UNIQUE constraint failed')) {
                throw AccommodationDomainException::codeTaken();
            }

            if (str_contains($message, '1451') || str_contains($message, 'FOREIGN KEY constraint failed')) {
                throw AccommodationDomainException::referenced();
            }

            if (str_contains($message, '1452') || str_contains($message, '4025') || str_contains($message, 'CHECK constraint failed')) {
                throw AccommodationDomainException::invalidReference();
            }

            throw $exception;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function utcInterval(string $startsAt, string $endsAt): array
    {
        $start = Carbon::parse($startsAt)->utc();
        $end = Carbon::parse($endsAt)->utc();

        if ($end->lessThanOrEqualTo($start)) {
            throw AccommodationDomainException::intervalInvalid();
        }

        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }
}
