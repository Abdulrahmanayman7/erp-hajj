<?php

namespace Database\Factories;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Enums\RecommendationStatus;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingRecommendation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingRecommendation>
 */
class MeetingRecommendationFactory extends Factory
{
    protected $model = MeetingRecommendation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'agenda_item_id' => null,
            'title' => fake()->sentence(3),
            'description' => null,
            'status' => RecommendationStatus::Draft,
            'owner_employee_id' => null,
            'sort_order' => 0,
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
}
