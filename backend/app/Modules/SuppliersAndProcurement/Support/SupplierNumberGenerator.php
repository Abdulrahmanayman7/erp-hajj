<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tenant-scoped SUP-###### allocator (config prefix/pad).
 *
 * Cold-start under MariaDB/MySQL REPEATABLE READ: the prior SELECT-miss → INSERT
 * → re-SELECT FOR UPDATE path could throw SUPPLIER_NUMBER_TAKEN when a concurrent
 * first insert left the loser observing a null row. Allocation is now one atomic
 * INSERT … ON DUPLICATE KEY UPDATE using LAST_INSERT_ID (same proven pattern as
 * PurchaseRequestNumberGenerator). Scope remains tenant_id primary key only.
 */
final class SupplierNumberGenerator
{
    private const TRANSACTION_ATTEMPTS = 5;

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function next(): string
    {
        $tenant = $this->tenantContext->require();
        $prefix = (string) config('suppliers.number_prefix', 'SUP-');
        $pad = max(1, (int) config('suppliers.number_pad', 6));

        $allocate = function () use ($tenant, $prefix, $pad): string {
            $now = now()->format('Y-m-d H:i:s');

            DB::affectingStatement(
                'INSERT INTO supplier_number_sequences
                    (tenant_id, next_value, created_at, updated_at)
                 VALUES (?, LAST_INSERT_ID(1) + 1, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    next_value = LAST_INSERT_ID(next_value) + 1,
                    updated_at = VALUES(updated_at)',
                [$tenant->id, $now, $now],
            );

            $value = (int) (DB::selectOne('SELECT LAST_INSERT_ID() AS v')->v ?? 0);
            if ($value < 1) {
                throw SupplierDomainException::numberTaken();
            }

            return $prefix.str_pad((string) $value, $pad, '0', STR_PAD_LEFT);
        };

        if (DB::transactionLevel() > 0) {
            return $allocate();
        }

        try {
            return DB::transaction($allocate, self::TRANSACTION_ATTEMPTS);
        } catch (Throwable $e) {
            if ($e instanceof SupplierDomainException) {
                throw $e;
            }

            throw SupplierDomainException::numberTaken();
        }
    }
}
