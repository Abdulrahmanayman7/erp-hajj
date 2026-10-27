<?php

namespace App\Modules\Accommodation\Support;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Modules\Accommodation\Models\AccommodationFacility;
use App\Modules\Accommodation\Models\AccommodationPosition;
use App\Modules\Accommodation\Models\AccommodationUnit;
use Illuminate\Support\Facades\DB;

final class AccommodationCodeGenerator
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function next(string $kind): string
    {
        [$prefix, $model] = match ($kind) {
            'hotel' => ['HTL-', AccommodationFacility::class],
            'camp' => ['CMP-', AccommodationFacility::class],
            'room' => ['RM-', AccommodationUnit::class],
            'tent' => ['TNT-', AccommodationUnit::class],
            'position' => ['BED-', AccommodationPosition::class],
        };
        $tenant = $this->tenants->require();

        return DB::transaction(function () use ($tenant, $prefix, $model): string {
            // Also serializes initialization when this tenant has no sequence row yet.
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $keys = ['tenant_id' => $tenant->id, 'prefix' => $prefix];
            $sequence = DB::table('accommodation_code_sequences')->where($keys)->lockForUpdate()->first();
            $number = (int) ($sequence->next_number ?? 1);

            do {
                $code = $prefix.str_pad((string) $number++, 6, '0', STR_PAD_LEFT);
            } while ($model::query()->where('code', $code)->exists());

            if ($sequence === null) {
                DB::table('accommodation_code_sequences')->insert([
                    ...$keys, 'next_number' => $number, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                DB::table('accommodation_code_sequences')->where($keys)->update([
                    'next_number' => $number, 'updated_at' => now(),
                ]);
            }

            return $code;
        });
    }
}
