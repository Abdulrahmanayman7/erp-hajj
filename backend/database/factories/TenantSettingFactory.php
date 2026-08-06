<?php

namespace Database\Factories;

use App\Core\Tenancy\Models\TenantSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * tenant_id is intentionally absent from the definition: the UsesTenantScope
 * trait force-sets it from TenantContext, so factory usage must be wrapped
 * in withTenant()/runAsTenant() — exactly like production code.
 *
 * @extends Factory<TenantSetting>
 */
class TenantSettingFactory extends Factory
{
    protected $model = TenantSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'setting_'.fake()->unique()->numberBetween(1000, 999999),
            'value' => ['enabled' => fake()->boolean()],
        ];
    }
}
