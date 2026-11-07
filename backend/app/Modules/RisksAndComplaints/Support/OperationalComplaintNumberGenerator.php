<?php

namespace App\Modules\RisksAndComplaints\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\RisksAndComplaints\Exceptions\RisksAndComplaintsDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Allocates CMP-YYYY-###### per tenant + season + year (S07-D06).
 */
final class OperationalComplaintNumberGenerator
{
    private const TABLE = 'operational_complaint_number_sequences';

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function next(Season $season, ?int $year = null): string
    {
        $tenant = $this->tenantContext->require();
        $year ??= (int) now()->year;
        $prefix = (string) config('risks_complaints.complaint_number_prefix', 'CMP-');
        $pad = max(1, (int) config('risks_complaints.number_pad', 6));

        $keys = [
            'tenant_id' => $tenant->id,
            'season_id' => $season->id,
            'year' => $year,
        ];

        $allocate = function () use ($keys, $prefix, $pad, $year): string {
            $row = DB::table(self::TABLE)->where($keys)->lockForUpdate()->first();

            if ($row === null) {
                try {
                    DB::table(self::TABLE)->insert([
                        ...$keys,
                        'next_number' => 2,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $value = 1;
                } catch (Throwable) {
                    $row = DB::table(self::TABLE)->where($keys)->lockForUpdate()->first();

                    if ($row === null) {
                        throw RisksAndComplaintsDomainException::complaintNumberUnavailable();
                    }

                    $value = (int) $row->next_number;
                    DB::table(self::TABLE)->where($keys)->update([
                        'next_number' => $value + 1,
                        'updated_at' => now(),
                    ]);
                }
            } else {
                $value = (int) $row->next_number;
                DB::table(self::TABLE)->where($keys)->update([
                    'next_number' => $value + 1,
                    'updated_at' => now(),
                ]);
            }

            return $prefix.$year.'-'.str_pad((string) $value, $pad, '0', STR_PAD_LEFT);
        };

        if (DB::transactionLevel() > 0) {
            return $allocate();
        }

        return DB::transaction($allocate);
    }
}
