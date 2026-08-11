<?php

namespace App\Modules\Meetings\Enums;

enum MeetingAttendanceStatus: string
{
    case Invited = 'invited';
    case Attended = 'attended';
    case Absent = 'absent';
    case Excused = 'excused';
}
