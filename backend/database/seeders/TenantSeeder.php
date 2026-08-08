<?php

namespace Database\Seeders;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantStatus;
use Illuminate\Database\Seeder;

/**
 * Provisions the first tenant exactly as documented
 * (docs/09-modules/00-tenancy/DATA_MODEL.md — initial provisioning values).
 * Idempotent: keyed on the immutable tenant_code.
 */
class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()->firstOrCreate(
            ['tenant_code' => 'rafee'],
            [
                'name' => 'رفيع',
                'status' => TenantStatus::Active,
                'locale' => 'ar',
                'timezone' => 'Asia/Riyadh',
            ],
        );
    }
}
