<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Enums\MeetingLocationType;
use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => null,
            'status' => MeetingStatus::Draft,
            'scheduled_at' => null,
            'started_at' => null,
            'ended_at' => null,
            'location_type' => MeetingLocationType::Physical,
            'location_text' => null,
            'meeting_link' => null,
            'organization_unit_id' => null,
            'chairperson_employee_id' => null,
            'secretary_employee_id' => null,
            'minutes_body' => null,
            'notes' => null,
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
            'status' => MeetingStatus::Draft,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => MeetingStatus::Scheduled,
            'scheduled_at' => now()->addDay(),
        ]);
    }
}
