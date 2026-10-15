<?php

namespace App\Modules\Transportation\Support;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Operational write transaction. InnoDB may still pick a deadlock victim
 * (e.g. gap locks on two Runs that have no assignment row yet); the
 * framework then rolls back and re-runs the whole callback, which re-reads
 * committed state and returns the normal domain outcome. Retries only
 * happen when this is the outermost transaction.
 */
final class TransportTransaction
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
