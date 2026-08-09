<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Exceptions\MeetingDomainException;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingAttendee;
use App\Modules\Meetings\Support\MeetingReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RemoveMeetingAttendee
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Meeting $meeting, MeetingAttendee $attendee, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $meeting, $attendee, $request, $tenant): void {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->references->assertMutable($locked);

            $row = MeetingAttendee::query()
                ->whereKey($attendee->id)
                ->where('meeting_id', $locked->id)
                ->first();

            if ($row === null) {
                throw MeetingDomainException::attendeeInvalid();
            }

            $snapshot = [
                'id' => $row->id,
                'employee_id' => $row->employee_id,
            ];

            $row->delete();

            $this->security->record(AuthorizationSecurityEvent::MEETING_ATTENDEE_REMOVED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'meeting_id' => $locked->id,
                'attendee' => $snapshot,
            ], $request);
        });
    }
}
