<?php

namespace App\Modules\Accommodation\Support;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Outermost write transaction for Accommodation concurrency (Gate B+).
 * Matches Transportation deadlock-retry attempts.
 */
final class AccommodationTransaction
{
    public const ATTEMPTS = 3;

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(Closure $callback): mixed
    {
        return DB::transaction($callback, self::ATTEMPTS);
    }
}
