<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Tasks\Enums\TaskPriority;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => null,
            'notes' => null,
            'status' => TaskStatus::Draft,
            'priority' => TaskPriority::Medium,
            'decision_id' => null,
            'organization_unit_id' => null,
            'assigned_to_employee_id' => null,
            'progress_percent' => 0,
            'start_date' => null,
            'due_date' => null,
            'completed_at' => null,
            'completion_notes' => null,
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
            'status' => TaskStatus::Draft,
            'assigned_to_employee_id' => null,
        ]);
    }

    public function assigned(): static
    {
        return $this->state(fn (): array => [
            'status' => TaskStatus::Assigned,
        ]);
    }
}
