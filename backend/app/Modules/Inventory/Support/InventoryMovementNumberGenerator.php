<?php

namespace App\Modules\Inventory\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\Inventory\Exceptions\InventoryDomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class InventoryMovementNumberGenerator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function next(): string
    {
        $tenant = $this->tenantContext->require();
        $prefix = (string) config('inventory.movement_number_prefix', 'MOV-');
        $pad = max(1, (int) config('inventory.movement_number_pad', 6));

        $allocate = function () use ($tenant, $prefix, $pad): string {
            $row = DB::table('inventory_movement_number_sequences')
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                try {
                    DB::table('inventory_movement_number_sequences')->insert([
                        'tenant_id' => $tenant->id,
                        'next_number' => 2,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $value = 1;
                } catch (Throwable) {
                    $row = DB::table('inventory_movement_number_sequences')
                        ->where('tenant_id', $tenant->id)
                        ->lockForUpdate()
                        ->first();

                    if ($row === null) {
                        throw InventoryDomainException::numberTaken();
                    }

                    $value = (int) $row->next_number;
                    DB::table('inventory_movement_number_sequences')
                        ->where('tenant_id', $tenant->id)
                        ->update([
                            'next_number' => $value + 1,
                            'updated_at' => now(),
                        ]);
                }
            } else {
                $value = (int) $row->next_number;
                DB::table('inventory_movement_number_sequences')
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
