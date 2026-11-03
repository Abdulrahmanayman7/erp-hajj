<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class GoodsReceiptNumberGenerator
{
    private const TRANSACTION_ATTEMPTS = 5;

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function next(): string
    {
        $tenant = $this->tenantContext->require();
        $prefix = 'GRN-';
        $pad = 6;

        $allocate = function () use ($tenant, $prefix, $pad): string {
            $now = now()->format('Y-m-d H:i:s');

            $row = DB::table('goods_receipt_number_sequences')
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('goods_receipt_number_sequences')->insert([
                    'tenant_id' => $tenant->id,
                    'next_value' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $value = 1;
            } else {
                $value = (int) $row->next_value;
                DB::table('goods_receipt_number_sequences')
                    ->where('tenant_id', $tenant->id)
                    ->update([
                        'next_value' => $value + 1,
                        'updated_at' => $now,
                    ]);
            }

            if ($value < 1) {
                throw SupplierDomainException::grnNumberConflict();
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

            throw SupplierDomainException::grnNumberConflict();
        }
    }
}
