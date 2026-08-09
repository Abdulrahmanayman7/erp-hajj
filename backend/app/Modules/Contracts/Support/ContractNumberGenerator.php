<?php

namespace App\Modules\Contracts\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\Contracts\Exceptions\ContractDomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Concurrency-safe per-tenant contract number allocation (CTR-######).
 *
 * Never uses unprotected MAX(contract_number)+1.
 */
final class ContractNumberGenerator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function next(): string
    {
        $tenant = $this->tenantContext->require();
        $prefix = (string) config('contracts.number_prefix', 'CTR-');
        $pad = max(1, (int) config('contracts.number_pad', 6));

        $allocate = function () use ($tenant, $prefix, $pad): string {
            $row = DB::table('contract_number_sequences')
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                try {
                    DB::table('contract_number_sequences')->insert([
                        'tenant_id' => $tenant->id,
                        'next_value' => 2,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $value = 1;
                } catch (Throwable) {
                    $row = DB::table('contract_number_sequences')
                        ->where('tenant_id', $tenant->id)
                        ->lockForUpdate()
                        ->first();

                    if ($row === null) {
                        throw ContractDomainException::numberTaken();
                    }

                    $value = (int) $row->next_value;
                    DB::table('contract_number_sequences')
                        ->where('tenant_id', $tenant->id)
                        ->update([
                            'next_value' => $value + 1,
                            'updated_at' => now(),
                        ]);
                }
            } else {
                $value = (int) $row->next_value;
                DB::table('contract_number_sequences')
                    ->where('tenant_id', $tenant->id)
                    ->update([
                        'next_value' => $value + 1,
                        'updated_at' => now(),
                    ]);
            }

            return $prefix.str_pad((string) $value, $pad, '0', STR_PAD_LEFT);
        };

        if (DB::transactionLevel() > 0) {
            return $allocate();
        }

        return DB::transaction($allocate);
    }
}
