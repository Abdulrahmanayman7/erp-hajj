<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Outermost banking Action transaction (WP-02).
 *
 * Owns the single bounded deadlock retry for the complete Action callback.
 * Nested BankIbanClaimService calls must not independently retry after MySQL
 * aborts the underlying transaction (WP-01 R1 caller contract).
 */
final class BankingTransaction
{
    /** Initial attempt + one deadlock retry. */
    public const ATTEMPTS = 2;

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
