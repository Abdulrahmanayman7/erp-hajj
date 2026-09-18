<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Enums\MeetingAttendanceStatus;
use App\Modules\Meetings\Exceptions\MeetingDomainException;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingAttendee;
use App\Modules\Meetings\Support\MeetingReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateMeetingAttendance
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(
        User $actor,
        Meeting $meeting,
        MeetingAttendee $attendee,
        string $attendanceStatus,
        Request $request,
    ): MeetingAttendee {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $meeting, $attendee, $attendanceStatus, $request, $tenant): MeetingAttendee {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->references->assertMutable($locked);

            $row = MeetingAttendee::query()
                ->whereKey($attendee->id)
                ->where('meeting_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw MeetingDomainException::attendeeInvalid();
            }

            $row->attendance_status = MeetingAttendanceStatus::from($attendanceStatus);
            $row->save();

            $this->security->record(AuthorizationSecurityEvent::MEETING_ATTENDANCE_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'meeting_id' => $locked->id,
                'attendee_id' => $row->id,
                'attendance_status' => $row->attendance_status->value,
            ], $request);

            return $row->load('employee');
        });
    }
}
