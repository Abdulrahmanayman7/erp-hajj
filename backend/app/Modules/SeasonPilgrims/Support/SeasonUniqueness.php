<?php

namespace App\Modules\SeasonPilgrims\Support;

use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * F13/F14: code and hajj_year are unique per tenant. Checked up front for
 * stable error codes; the database unique indexes remain the backstop.
 */
final class SeasonUniqueness
{
    public function assertAvailable(string $code, int $hajjYear, ?int $ignoreSeasonId = null): void
    {
        $query = fn () => Season::query()->when(
            $ignoreSeasonId !== null,
            fn ($q) => $q->whereKeyNot($ignoreSeasonId),
        );

        if ($query()->where('code', $code)->exists()) {
            throw SeasonPilgrimDomainException::seasonCodeTaken();
        }

        if ($query()->where('hajj_year', $hajjYear)->exists()) {
            throw SeasonPilgrimDomainException::seasonHajjYearTaken();
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $write
     * @return T
     */
    public function guard(Closure $write): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException $e) {
            $message = $e->getMessage();

            throw match (true) {
                str_contains($message, 'hajj_year') => SeasonPilgrimDomainException::seasonHajjYearTaken(),
                str_contains($message, 'active') => SeasonPilgrimDomainException::seasonActiveExists(),
                default => SeasonPilgrimDomainException::seasonCodeTaken(),
            };
        }
    }
}
