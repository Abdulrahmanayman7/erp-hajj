<?php

namespace Database\Factories;

use App\Modules\Inventory\Models\InventoryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCategory>
 */
class InventoryCategoryFactory extends Factory
{
    protected $model = InventoryCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
