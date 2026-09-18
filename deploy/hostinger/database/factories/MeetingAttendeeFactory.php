<?php

namespace Database\Factories;

use App\Modules\Employees\Models\Employee;
use App\Modules\Meetings\Enums\MeetingAttendanceStatus;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingAttendee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingAttendee>
 */
class MeetingAttendeeFactory extends Factory
{
    protected $model = MeetingAttendee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'employee_id' => Employee::factory(),
            'attendance_status' => MeetingAttendanceStatus::Invited,
        ];
    }
}
