<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tenant-scoped PR-###### allocator.
 *
 * Cold-start under MariaDB/MySQL REPEATABLE READ:
 * The previous "SELECT miss → INSERT → on duplicate re-SELECT FOR UPDATE"
 * path could raise PR_NUMBER_CONFLICT when the loser of a first-insert race
 * still observed a null row (duplicate-key / deadlock / visibility window).
 *
 * Allocation is now one atomic statement:
 *   INSERT … VALUES (tenant, LAST_INSERT_ID(1)+1, …)  -- seeds next_value=2, issues 1
 *   ON DUPLICATE KEY UPDATE next_value = LAST_INSERT_ID(next_value)+1
 * Then READ LAST_INSERT_ID() for the issued value. Concurrent first allocators
 * serialize on the primary key without a SELECT-miss race. Nested callers rely
 * on the outer unit-of-work's deadlock retries; standalone callers use bounded
 * Laravel transaction attempts.
 */
final class PurchaseRequestNumberGenerator
{
    private const TRANSACTION_ATTEMPTS = 5;

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function next(): string
    {
        $tenant = $this->tenantContext->require();
        $prefix = 'PR-';
        $pad = 6;

        $allocate = function () use ($tenant, $prefix, $pad): string {
            $now = now()->format('Y-m-d H:i:s');

            // Atomic init-or-increment. LAST_INSERT_ID(expr) publishes the issued
            // value on both the INSERT and UPDATE paths for this connection.
            DB::affectingStatement(
                'INSERT INTO purchase_request_number_sequences
                    (tenant_id, next_value, created_at, updated_at)
                 VALUES (?, LAST_INSERT_ID(1) + 1, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    next_value = LAST_INSERT_ID(next_value) + 1,
                    updated_at = VALUES(updated_at)',
                [$tenant->id, $now, $now],
            );

            $value = (int) (DB::selectOne('SELECT LAST_INSERT_ID() AS v')->v ?? 0);
            if ($value < 1) {
                throw SupplierDomainException::prNumberConflict();
            }

            return $prefix.str_pad((string) $value, $pad, '0', STR_PAD_LEFT);
        };

        if (DB::transactionLevel() > 0) {
            try {
                return $allocate();
            } catch (Throwable $e) {
                if ($e instanceof SupplierDomainException) {
                    throw $e;
                }
                // Propagate engine contention to the outer transaction retries.
                throw $e;
            }
        }

        try {
            return DB::transaction($allocate, self::TRANSACTION_ATTEMPTS);
        } catch (Throwable $e) {
            if ($e instanceof SupplierDomainException) {
                throw $e;
            }

            throw SupplierDomainException::prNumberConflict();
        }
    }
}
