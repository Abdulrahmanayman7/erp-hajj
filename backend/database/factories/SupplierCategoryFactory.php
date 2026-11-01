<?php

namespace Database\Factories;

use App\Modules\SuppliersAndProcurement\Models\SupplierCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierCategory>
 */
class SupplierCategoryFactory extends Factory
{
    protected $model = SupplierCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('SC_????')),
            'is_active' => true,
        ];
    }
}
