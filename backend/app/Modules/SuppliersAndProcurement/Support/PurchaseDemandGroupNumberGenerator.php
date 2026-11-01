<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tenant-scoped DG-###### allocator (mirrors PurchaseRequestNumberGenerator).
 */
final class PurchaseDemandGroupNumberGenerator
{
    private const TRANSACTION_ATTEMPTS = 5;

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function next(): string
    {
        $tenant = $this->tenantContext->require();
        $prefix = 'DG-';
        $pad = 6;

        $allocate = function () use ($tenant, $prefix, $pad): string {
            $now = now()->format('Y-m-d H:i:s');

            DB::affectingStatement(
                'INSERT INTO purchase_demand_group_number_sequences
                    (tenant_id, next_value, created_at, updated_at)
                 VALUES (?, LAST_INSERT_ID(1) + 1, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    next_value = LAST_INSERT_ID(next_value) + 1,
                    updated_at = VALUES(updated_at)',
                [$tenant->id, $now, $now],
            );

            $value = (int) (DB::selectOne('SELECT LAST_INSERT_ID() AS v')->v ?? 0);
            if ($value < 1) {
                throw SupplierDomainException::dgNumberConflict();
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
                throw $e;
            }
        }

        try {
            return DB::transaction($allocate, self::TRANSACTION_ATTEMPTS);
        } catch (Throwable $e) {
            if ($e instanceof SupplierDomainException) {
                throw $e;
            }

            throw SupplierDomainException::dgNumberConflict();
        }
    }
}
