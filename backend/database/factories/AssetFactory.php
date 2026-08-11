<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => null,
            'category_id' => null,
            'serial_number' => null,
            'barcode' => null,
            'status' => AssetStatus::Available,
            'condition' => null,
            'warehouse_id' => null,
            'organization_unit_id' => null,
            'purchase_value' => null,
            'acquisition_date' => null,
            'notes' => null,
            'created_by' => function (): int {
                $attrs = [];
                $tenant = app(TenantContext::class)->get();
                if ($tenant !== null) {
                    $attrs['tenant_id'] = $tenant->id;
                }

                return User::factory()->create($attrs)->id;
            },
        ];
    }

    public function available(): static
    {
        return $this->state(fn (): array => [
            'status' => AssetStatus::Available,
            'current_custody_id' => null,
        ]);
    }
}
