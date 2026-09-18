<?php

namespace Database\Factories;

use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitType;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationUnit>
 */
class OrganizationUnitFactory extends Factory
{
    protected $model = OrganizationUnit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('UNIT_????'));

        return [
            'parent_id' => null,
            'name' => fake()->unique()->words(2, true),
            'code' => $code,
            'type' => OrganizationUnitType::Department,
            'status' => OrganizationUnitStatus::Active,
            'manager_user_id' => null,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganizationUnitStatus::Inactive,
        ]);
    }

    public function section(): static
    {
        return $this->state(fn (): array => [
            'type' => OrganizationUnitType::Section,
        ]);
    }

    public function unit(): static
    {
        return $this->state(fn (): array => [
            'type' => OrganizationUnitType::Unit,
        ]);
    }

    public function childOf(OrganizationUnit $parent): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $parent->id,
        ]);
    }
}
