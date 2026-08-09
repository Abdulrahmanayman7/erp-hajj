<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingAgendaItem;
use App\Modules\Meetings\Models\MeetingAttendee;
use App\Modules\Meetings\Models\MeetingRecommendation;
use App\Modules\Meetings\Models\MeetingStatusTransition;
use App\Modules\Meetings\Support\MeetingReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteMeeting
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(User $actor, Meeting $meeting, Request $request): void
    {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $meeting, $request, $tenant): void {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->references->assertDeletableDraft($locked);

            $snapshot = [
                'id' => $locked->id,
                'meeting_number' => $locked->meeting_number,
                'title' => $locked->title,
            ];

            MeetingAttendee::query()->where('meeting_id', $locked->id)->delete();
            MeetingRecommendation::query()->where('meeting_id', $locked->id)->delete();
            MeetingAgendaItem::query()->where('meeting_id', $locked->id)->delete();
            MeetingStatusTransition::query()->where('meeting_id', $locked->id)->delete();
            $locked->delete();

            $this->security->record(AuthorizationSecurityEvent::MEETING_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'meeting' => $snapshot,
            ], $request);
        });
    }
}
