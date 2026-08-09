<?php

namespace Database\Factories;

use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingAgendaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingAgendaItem>
 */
class MeetingAgendaItemFactory extends Factory
{
    protected $model = MeetingAgendaItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'title' => fake()->sentence(3),
            'description' => null,
            'sort_order' => 0,
        ];
    }
}
