<?php

namespace App\Modules\Employees\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\Employees\Exceptions\EmployeeDomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Concurrency-safe per-tenant employee number allocation.
 *
 * Uses a dedicated sequence row with SELECT … FOR UPDATE inside the caller's
 * transaction (or opens one). Never uses unprotected MAX(employee_number)+1.
 */
final class EmployeeNumberGenerator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function next(): string
    {
        $tenant = $this->tenantContext->require();
        $prefix = (string) config('employees.number_prefix', 'EMP-');
        $pad = max(1, (int) config('employees.number_pad', 6));

        $allocate = function () use ($tenant, $prefix, $pad): string {
            $row = DB::table('employee_number_sequences')
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                try {
                    DB::table('employee_number_sequences')->insert([
                        'tenant_id' => $tenant->id,
                        'next_value' => 2,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $value = 1;
                } catch (Throwable) {
                    $row = DB::table('employee_number_sequences')
                        ->where('tenant_id', $tenant->id)
                        ->lockForUpdate()
                        ->first();

                    if ($row === null) {
                        throw EmployeeDomainException::numberTaken();
                    }

                    $value = (int) $row->next_value;
                    DB::table('employee_number_sequences')
                        ->where('tenant_id', $tenant->id)
                        ->update([
                            'next_value' => $value + 1,
                            'updated_at' => now(),
                        ]);
                }
            } else {
                $value = (int) $row->next_value;
                DB::table('employee_number_sequences')
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
