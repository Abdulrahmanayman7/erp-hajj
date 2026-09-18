<?php

namespace Database\Factories;

use App\Modules\Contracts\Models\ContractCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractCategory>
 */
class ContractCategoryFactory extends Factory
{
    protected $model = ContractCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('CAT_????')),
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
