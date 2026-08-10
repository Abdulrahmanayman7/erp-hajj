<?php

namespace App\Modules\Tasks\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\Tasks\Exceptions\TaskDomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class TaskNumberGenerator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function next(): string
    {
        $tenant = $this->tenantContext->require();
        $prefix = (string) config('tasks.number_prefix', 'TSK-');
        $pad = max(1, (int) config('tasks.number_pad', 6));

        $allocate = function () use ($tenant, $prefix, $pad): string {
            $row = DB::table('task_number_sequences')
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                try {
                    DB::table('task_number_sequences')->insert([
                        'tenant_id' => $tenant->id,
                        'next_number' => 2,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $value = 1;
                } catch (Throwable) {
                    $row = DB::table('task_number_sequences')
                        ->where('tenant_id', $tenant->id)
                        ->lockForUpdate()
                        ->first();

                    if ($row === null) {
                        throw TaskDomainException::numberTaken();
                    }

                    $value = (int) $row->next_number;
                    DB::table('task_number_sequences')
                        ->where('tenant_id', $tenant->id)
                        ->update([
                            'next_number' => $value + 1,
                            'updated_at' => now(),
                        ]);
                }
            } else {
                $value = (int) $row->next_number;
                DB::table('task_number_sequences')
                    ->where('tenant_id', $tenant->id)
                    ->update([
                        'next_number' => $value + 1,
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
