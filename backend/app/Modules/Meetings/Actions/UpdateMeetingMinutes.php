<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Support\MeetingReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateMeetingMinutes
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Meeting $meeting, ?string $minutesBody, Request $request): Meeting
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $meeting, $minutesBody, $request, $tenant): Meeting {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->references->assertMutable($locked);

            $locked->minutes_body = $minutesBody;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::MEETING_MINUTES_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'meeting_id' => $locked->id,
                'meeting_number' => $locked->meeting_number,
            ], $request);

            return $locked->load([
                'organizationUnit',
                'chairperson',
                'secretary',
                'createdBy',
                'attendees.employee',
                'agendaItems',
                'recommendations.owner',
                'recommendations.createdBy',
                'statusTransitions.actor',
            ]);
        });
    }
}
