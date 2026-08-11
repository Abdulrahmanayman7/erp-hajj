<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Enums\RecommendationStatus;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingRecommendation;
use App\Modules\Meetings\Support\MeetingReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateMeetingRecommendation
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Meeting $meeting, array $data, Request $request): MeetingRecommendation
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $meeting, $data, $request, $tenant): MeetingRecommendation {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->references->assertMutable($locked);

            $agendaItemId = array_key_exists('agenda_item_id', $data) && $data['agenda_item_id'] !== null
                ? (int) $data['agenda_item_id']
                : null;
            $agendaItem = $this->references->assertAgendaItemBelongsToMeeting($agendaItemId, $locked);

            $owner = $this->references->resolveAssignableEmployee(
                array_key_exists('owner_employee_id', $data) && $data['owner_employee_id'] !== null
                    ? (int) $data['owner_employee_id']
                    : null,
            );

            $status = $data['status'] ?? RecommendationStatus::Draft->value;

            $recommendation = new MeetingRecommendation([
                'meeting_id' => $locked->id,
                'agenda_item_id' => $agendaItem?->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => RecommendationStatus::from($status),
                'owner_employee_id' => $owner?->id,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'created_by' => $actor->id,
            ]);
            $recommendation->save();

            $this->security->record(AuthorizationSecurityEvent::MEETING_RECOMMENDATION_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'meeting_id' => $locked->id,
                'recommendation_id' => $recommendation->id,
            ], $request);

            return $recommendation->load(['owner', 'createdBy']);
        });
    }
}
