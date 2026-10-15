<?php

namespace App\Modules\Transportation\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Business references: VEH-###### per tenant, MOV-###### per season. The
 * sequence row is locked for update and only moves forward.
 */
final class TransportNumberGenerator
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function nextVehicleNumber(): string
    {
        $tenant = $this->tenantContext->require();

        return $this->allocate(
            'transport_vehicle_sequences',
            ['tenant_id' => $tenant->id],
            (string) config('transportation.vehicle_number_prefix', 'VEH-'),
        );
    }

    public function nextMovementNumber(Season $season): string
    {
        $tenant = $this->tenantContext->require();

        return $this->allocate(
            'transport_movement_sequences',
            ['tenant_id' => $tenant->id, 'season_id' => $season->id],
            (string) config('transportation.movement_number_prefix', 'MOV-'),
        );
    }

    /**
     * @param  array<string, int>  $keys
     */
    private function allocate(string $table, array $keys, string $prefix): string
    {
        $pad = max(1, (int) config('transportation.number_pad', 6));

        $allocate = function () use ($table, $keys, $prefix, $pad): string {
            $row = DB::table($table)->where($keys)->lockForUpdate()->first();

            if ($row === null) {
                try {
                    DB::table($table)->insert([...$keys, 'next_number' => 2, 'created_at' => now(), 'updated_at' => now()]);

                    return $prefix.str_pad('1', $pad, '0', STR_PAD_LEFT);
                } catch (Throwable) {
                    $row = DB::table($table)->where($keys)->lockForUpdate()->first()
                        ?? throw TransportationDomainException::numberUnavailable();
                }
            }

            $value = (int) $row->next_number;
            DB::table($table)->where($keys)->update(['next_number' => $value + 1, 'updated_at' => now()]);

            return $prefix.str_pad((string) $value, $pad, '0', STR_PAD_LEFT);
        };

        return DB::transactionLevel() > 0 ? $allocate() : DB::transaction($allocate);
    }
}
