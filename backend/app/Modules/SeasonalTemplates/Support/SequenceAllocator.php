<?php

namespace App\Modules\SeasonalTemplates\Support;

use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Per-tenant gap-tolerant counter (`tenant_id` PK, `next_number`) guarded by a
 * row lock. The sequence only advances.
 */
final class SequenceAllocator
{
    public function next(string $table, int $tenantId): int
    {
        $allocate = function () use ($table, $tenantId): int {
            $row = DB::table($table)->where('tenant_id', $tenantId)->lockForUpdate()->first();

            if ($row === null) {
                try {
                    DB::table($table)->insert([
                        'tenant_id' => $tenantId,
                        'next_number' => 2,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return 1;
                } catch (Throwable) {
                    $row = DB::table($table)->where('tenant_id', $tenantId)->lockForUpdate()->first();

                    if ($row === null) {
                        throw SeasonalTemplatesDomainException::numberUnavailable();
                    }
                }
            }

            $value = (int) $row->next_number;
            DB::table($table)->where('tenant_id', $tenantId)->update([
                'next_number' => $value + 1,
                'updated_at' => now(),
            ]);

            return $value;
        };

        if (DB::transactionLevel() > 0) {
            return $allocate();
        }

        return DB::transaction($allocate);
    }
}
