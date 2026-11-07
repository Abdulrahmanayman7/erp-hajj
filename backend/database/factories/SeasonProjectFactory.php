<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonProjects\Enums\SeasonProjectStatus;
use App\Modules\SeasonProjects\Models\SeasonProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonProject>
 */
class SeasonProjectFactory extends Factory
{
    protected $model = SeasonProject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => function (): int {
                $attrs = [];
                $tenant = app(TenantContext::class)->get();
                if ($tenant !== null) {
                    $attrs['tenant_id'] = $tenant->id;
                }

                return Season::factory()->create($attrs)->id;
            },
            'title' => fake()->sentence(3),
            'description' => null,
            'status' => SeasonProjectStatus::Draft,
            'owner_employee_id' => null,
            'budget_amount' => null,
            'currency_code' => 'SAR',
            'progress_percent' => 0,
            'delay_summary' => null,
            'created_by' => function (): ?int {
                $attrs = [];
                $tenant = app(TenantContext::class)->get();
                if ($tenant !== null) {
                    $attrs['tenant_id'] = $tenant->id;
                }

                return User::factory()->create($attrs)->id;
            },
            'updated_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => SeasonProjectStatus::Draft,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => SeasonProjectStatus::Active,
            'activated_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => SeasonProjectStatus::Completed,
            'activated_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => SeasonProjectStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
