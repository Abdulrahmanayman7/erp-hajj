<?php

namespace App\Modules\Meetings\Resources;

use App\Modules\Meetings\Models\MeetingAttendee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MeetingAttendee
 */
class MeetingAttendeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MeetingAttendee $row */
        $row = $this->resource;

        $employee = null;
        if ($row->relationLoaded('employee') && $row->employee !== null) {
            $employee = [
                'id' => $row->employee->id,
                'employee_number' => $row->employee->employee_number,
                'full_name' => $row->employee->full_name,
            ];
        }

        return [
            'id' => $row->id,
            'employee' => $employee,
            'attendance_status' => $row->attendance_status->value,
        ];
    }
}
