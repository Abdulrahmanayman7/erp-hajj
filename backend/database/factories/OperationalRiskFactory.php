<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\RisksAndComplaints\Enums\RiskSeverity;
use App\Modules\RisksAndComplaints\Enums\RiskStatus;
use App\Modules\RisksAndComplaints\Models\OperationalRisk;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalRisk>
 */
class OperationalRiskFactory extends Factory
{
    protected $model = OperationalRisk::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => function (): int {
                return Season::factory()->create()->id;
            },
            'title' => fake()->sentence(4),
            'description' => null,
            'status' => RiskStatus::Identified,
            'likelihood' => null,
            'impact' => null,
            'score' => null,
            'severity_level' => null,
            'closure_outcome' => null,
            'close_reason' => null,
            'created_by' => function (): int {
                $attrs = [];
                $tenant = app(TenantContext::class)->get();
                if ($tenant !== null) {
                    $attrs['tenant_id'] = $tenant->id;
                }

                return User::factory()->create($attrs)->id;
            },
            'approved_by' => null,
            'approved_at' => null,
            'owner_employee_id' => null,
            'season_pilgrim_id' => null,
            'group_id' => null,
            'facility_id' => null,
            'transport_season_vehicle_id' => null,
            'project_id' => null,
        ];
    }

    public function identified(): static
    {
        return $this->state(fn (): array => [
            'status' => RiskStatus::Identified,
        ]);
    }

    public function underAssessment(int $likelihood = 3, int $impact = 3): static
    {
        return $this->state(fn (): array => [
            'status' => RiskStatus::UnderAssessment,
            'likelihood' => $likelihood,
            'impact' => $impact,
        ]);
    }

    public function criticalBand(): static
    {
        return $this->underAssessment(5, 5)->state(fn (): array => [
            'severity_level' => RiskSeverity::Critical,
            'score' => 25,
        ]);
    }

    public function forSeason(Season $season): static
    {
        return $this->state(fn (): array => [
            'season_id' => $season->id,
        ]);
    }
}
