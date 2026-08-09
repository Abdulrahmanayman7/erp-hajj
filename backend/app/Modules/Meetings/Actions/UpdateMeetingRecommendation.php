<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Enums\RecommendationStatus;
use App\Modules\Meetings\Exceptions\MeetingDomainException;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingRecommendation;
use App\Modules\Meetings\Support\MeetingReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateMeetingRecommendation
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        User $actor,
        Meeting $meeting,
        MeetingRecommendation $recommendation,
        array $data,
        Request $request,
    ): MeetingRecommendation {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $meeting, $recommendation, $data, $request, $tenant): MeetingRecommendation {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->references->assertMutable($locked);

            $row = MeetingRecommendation::query()
                ->whereKey($recommendation->id)
                ->where('meeting_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw MeetingDomainException::recommendationNotFound();
            }

            if (array_key_exists('agenda_item_id', $data)) {
                $agendaItemId = $data['agenda_item_id'] !== null ? (int) $data['agenda_item_id'] : null;
                $agendaItem = $this->references->assertAgendaItemBelongsToMeeting($agendaItemId, $locked);
                $row->agenda_item_id = $agendaItem?->id;
            }

            if (array_key_exists('owner_employee_id', $data)) {
                $ownerId = $data['owner_employee_id'] !== null ? (int) $data['owner_employee_id'] : null;
                $owner = $this->references->resolveAssignableEmployee($ownerId, $row->owner_employee_id);
                $row->owner_employee_id = $owner?->id;
            }

            if (array_key_exists('title', $data)) {
                $row->title = $data['title'];
            }
            if (array_key_exists('description', $data)) {
                $row->description = $data['description'];
            }
            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $row->status = RecommendationStatus::from($data['status']);
            }
            if (array_key_exists('sort_order', $data)) {
                $row->sort_order = (int) $data['sort_order'];
            }

            $row->save();

            $this->security->record(AuthorizationSecurityEvent::MEETING_RECOMMENDATION_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'meeting_id' => $locked->id,
                'recommendation_id' => $row->id,
            ], $request);

            return $row->load(['owner', 'createdBy']);
        });
    }
}
