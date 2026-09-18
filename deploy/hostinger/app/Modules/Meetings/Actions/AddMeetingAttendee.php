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
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AddMeetingAttendee
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Meeting $meeting, int $employeeId, Request $request): MeetingAttendee
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $meeting, $employeeId, $request, $tenant): MeetingAttendee {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->references->assertMutable($locked);

            $employee = $this->references->resolveAssignableEmployee($employeeId);
            if ($employee === null) {
                throw MeetingDomainException::employeeInvalid();
            }

            $attendee = new MeetingAttendee([
                'meeting_id' => $locked->id,
                'employee_id' => $employee->id,
                'attendance_status' => MeetingAttendanceStatus::Invited,
            ]);

            $exists = MeetingAttendee::query()
                ->where('meeting_id', $locked->id)
                ->where('employee_id', $employee->id)
                ->exists();

            if ($exists) {
                throw MeetingDomainException::attendeeDuplicate();
            }

            try {
                $attendee->save();
            } catch (QueryException) {
                throw MeetingDomainException::attendeeDuplicate();
            }

            $this->security->record(AuthorizationSecurityEvent::MEETING_ATTENDEE_ADDED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'meeting_id' => $locked->id,
                'attendee_id' => $attendee->id,
                'employee_id' => $employee->id,
            ], $request);

            return $attendee->load('employee');
        });
    }
}
