<?php

namespace App\Modules\Meetings\Requests;

use App\Modules\Meetings\Enums\MeetingAttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attendance_status' => ['required', 'string', Rule::in([
                MeetingAttendanceStatus::Invited->value,
                MeetingAttendanceStatus::Attended->value,
                MeetingAttendanceStatus::Absent->value,
                MeetingAttendanceStatus::Excused->value,
            ])],
        ];
    }
}
