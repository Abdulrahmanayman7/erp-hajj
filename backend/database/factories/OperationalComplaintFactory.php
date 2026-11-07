<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\RisksAndComplaints\Enums\ComplaintStatus;
use App\Modules\RisksAndComplaints\Models\OperationalComplaint;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalComplaint>
 */
class OperationalComplaintFactory extends Factory
{
    protected $model = OperationalComplaint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = config('risks_complaints.complaint_categories', ['other']);

        return [
            'season_id' => function (): int {
                return Season::factory()->create()->id;
            },
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'category_code' => fake()->randomElement($categories),
            'status' => ComplaintStatus::New,
            'created_by' => function (): int {
                $attrs = [];
                $tenant = app(TenantContext::class)->get();
                if ($tenant !== null) {
                    $attrs['tenant_id'] = $tenant->id;
                }

                return User::factory()->create($attrs)->id;
            },
            'assignee_employee_id' => null,
            'escalation_level' => 0,
            'escalated_to_employee_id' => null,
            'escalate_reason' => null,
            'escalated_at' => null,
            'resolution_text' => null,
            'resolved_at' => null,
            'resolved_by' => null,
            'closed_at' => null,
            'closed_by' => null,
            'close_reason' => null,
            'reopened_at' => null,
            'reopened_by' => null,
            'reopen_reason' => null,
            'season_pilgrim_id' => null,
            'group_id' => null,
            'facility_id' => null,
            'transport_season_vehicle_id' => null,
            'project_id' => null,
        ];
    }

    public function asNew(): static
    {
        return $this->state(fn (): array => [
            'status' => ComplaintStatus::New,
            'assignee_employee_id' => null,
        ]);
    }

    public function underReview(): static
    {
        return $this->state(fn (): array => [
            'status' => ComplaintStatus::UnderReview,
        ]);
    }

    public function forSeason(Season $season): static
    {
        return $this->state(fn (): array => [
            'season_id' => $season->id,
        ]);
    }
}
