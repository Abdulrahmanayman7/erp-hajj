<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Exceptions\MeetingDomainException;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingRecommendation;
use App\Modules\Meetings\Support\MeetingReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeleteMeetingRecommendation
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    public function execute(
        User $actor,
        Meeting $meeting,
        MeetingRecommendation $recommendation,
        Request $request,
    ): void {
        $tenant = $this->tenantContext->require();

        DB::transaction(function () use ($actor, $meeting, $recommendation, $request, $tenant): void {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->references->assertMutable($locked);

            $row = MeetingRecommendation::query()
                ->whereKey($recommendation->id)
                ->where('meeting_id', $locked->id)
                ->first();

            if ($row === null) {
                throw MeetingDomainException::recommendationNotFound();
            }

            $snapshot = ['id' => $row->id, 'title' => $row->title];
            $row->delete();

            $this->security->record(AuthorizationSecurityEvent::MEETING_RECOMMENDATION_DELETED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'meeting_id' => $locked->id,
                'recommendation' => $snapshot,
            ], $request);
        });
    }
}
