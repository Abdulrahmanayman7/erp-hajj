<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\RisksAndComplaints\Enums\TreatmentStatus;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\RisksAndComplaints\Models\OperationalRiskTreatment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalRiskTreatment>
 */
class OperationalRiskTreatmentFactory extends Factory
{
    protected $model = OperationalRiskTreatment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'risk_id' => function (): int {
                return OperationalRisk::factory()->create()->id;
            },
            'title' => fake()->sentence(3),
            'plan_body' => fake()->paragraph(),
            'owner_employee_id' => function (): int {
                $unitAttrs = [];
                $tenant = app(TenantContext::class)->get();
                if ($tenant !== null) {
                    $unitAttrs['tenant_id'] = $tenant->id;
                }
                $unit = OrganizationUnit::factory()->create($unitAttrs);

                return Employee::factory()->create([
                    'organization_unit_id' => $unit->id,
                ])->id;
            },
            'due_at' => null,
            'status' => TreatmentStatus::Planned,
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

    public function planned(): static
    {
        return $this->state(fn (): array => [
            'status' => TreatmentStatus::Planned,
        ]);
    }
}
