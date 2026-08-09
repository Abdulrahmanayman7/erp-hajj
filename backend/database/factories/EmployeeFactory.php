<?php

namespace Database\Factories;

use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'phone' => null,
            'email' => null,
            'organization_unit_id' => OrganizationUnit::factory(),
            'position_id' => null,
            'supervisor_id' => null,
            'user_id' => null,
            'status' => EmployeeStatus::Active,
            'hire_date' => null,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => EmployeeStatus::Inactive,
        ]);
    }
}
