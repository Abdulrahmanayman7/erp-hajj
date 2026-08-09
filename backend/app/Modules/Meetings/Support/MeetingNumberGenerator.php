<?php

namespace App\Modules\Meetings\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\Meetings\Exceptions\MeetingDomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Concurrency-safe per-tenant meeting number allocation (MTG-######).
 *
 * Never uses unprotected MAX(meeting_number)+1.
 */
final class MeetingNumberGenerator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function next(): string
    {
        $tenant = $this->tenantContext->require();
        $prefix = (string) config('meetings.number_prefix', 'MTG-');
        $pad = max(1, (int) config('meetings.number_pad', 6));

        $allocate = function () use ($tenant, $prefix, $pad): string {
            $row = DB::table('meeting_number_sequences')
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                try {
                    DB::table('meeting_number_sequences')->insert([
                        'tenant_id' => $tenant->id,
                        'next_value' => 2,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $value = 1;
                } catch (Throwable) {
                    $row = DB::table('meeting_number_sequences')
                        ->where('tenant_id', $tenant->id)
                        ->lockForUpdate()
                        ->first();

                    if ($row === null) {
                        throw MeetingDomainException::numberTaken();
                    }

                    $value = (int) $row->next_value;
                    DB::table('meeting_number_sequences')
                        ->where('tenant_id', $tenant->id)
                        ->update([
                            'next_value' => $value + 1,
                            'updated_at' => now(),
                        ]);
                }
            } else {
                $value = (int) $row->next_value;
                DB::table('meeting_number_sequences')
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
