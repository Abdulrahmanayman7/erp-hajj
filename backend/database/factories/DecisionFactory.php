<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Decisions\Enums\DecisionStatus;
use App\Modules\Decisions\Models\Decision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Decision>
 */
class DecisionFactory extends Factory
{
    protected $model = Decision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'notes' => null,
            'status' => DecisionStatus::Draft,
            'source_recommendation_id' => null,
            'organization_unit_id' => null,
            'issued_by_employee_id' => null,
            'responsible_employee_id' => null,
            'effective_date' => null,
            'due_date' => null,
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

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => DecisionStatus::Draft,
        ]);
    }
}
