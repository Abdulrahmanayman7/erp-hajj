<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Inventory\Enums\InventoryUnit;
use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => null,
            'category_id' => null,
            'unit' => InventoryUnit::Piece->value,
            'barcode' => null,
            'minimum_stock' => '0.000',
            'is_active' => true,
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

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
