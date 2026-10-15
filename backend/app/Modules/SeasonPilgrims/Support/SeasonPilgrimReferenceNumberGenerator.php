<?php

namespace App\Modules\SeasonPilgrims\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Seasonal reference numbers (F32): per tenant + season sequence row,
 * locked for update; prefix/pad from config('season_pilgrims').
 * The sequence only moves forward, so numbers are never reused.
 */
final class SeasonPilgrimReferenceNumberGenerator
{
    private const TABLE = 'season_pilgrim_reference_sequences';

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function next(Season $season): string
    {
        $tenant = $this->tenantContext->require();
        $prefix = (string) config('season_pilgrims.reference_number_prefix', 'SP-');
        $pad = max(1, (int) config('season_pilgrims.reference_number_pad', 6));

        $keys = ['tenant_id' => $tenant->id, 'season_id' => $season->id];

        $allocate = function () use ($keys, $prefix, $pad): string {
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
                        throw SeasonPilgrimDomainException::referenceNumberUnavailable();
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

            return $prefix.str_pad((string) $value, $pad, '0', STR_PAD_LEFT);
        };

        if (DB::transactionLevel() > 0) {
            return $allocate();
        }

        return DB::transaction($allocate);
    }
}
