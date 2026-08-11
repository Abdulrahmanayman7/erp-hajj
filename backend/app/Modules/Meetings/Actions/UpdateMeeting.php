<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Enums\MeetingLocationType;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Support\MeetingReferenceValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateMeeting
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingReferenceValidator $references,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Meeting $meeting, array $data, Request $request): Meeting
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $meeting, $data, $request, $tenant): Meeting {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $this->references->assertMutable($locked);

            $unitId = array_key_exists('organization_unit_id', $data)
                ? ($data['organization_unit_id'] !== null ? (int) $data['organization_unit_id'] : null)
                : $locked->organization_unit_id;
            $unit = $this->references->resolveAssignableOrganizationUnit($unitId, $locked->organization_unit_id);

            $chairId = array_key_exists('chairperson_employee_id', $data)
                ? ($data['chairperson_employee_id'] !== null ? (int) $data['chairperson_employee_id'] : null)
                : $locked->chairperson_employee_id;
            $chair = $this->references->resolveAssignableEmployee($chairId, $locked->chairperson_employee_id);

            $secretaryId = array_key_exists('secretary_employee_id', $data)
                ? ($data['secretary_employee_id'] !== null ? (int) $data['secretary_employee_id'] : null)
                : $locked->secretary_employee_id;
            $secretary = $this->references->resolveAssignableEmployee($secretaryId, $locked->secretary_employee_id);

            if (array_key_exists('title', $data)) {
                $locked->title = $data['title'];
            }
            if (array_key_exists('description', $data)) {
                $locked->description = $data['description'];
            }
            if (array_key_exists('location_type', $data) && $data['location_type'] !== null) {
                $locked->location_type = MeetingLocationType::from($data['location_type']);
            }
            if (array_key_exists('location_text', $data)) {
                $locked->location_text = $data['location_text'];
            }
            if (array_key_exists('meeting_link', $data)) {
                $locked->meeting_link = $data['meeting_link'];
            }
            if (array_key_exists('scheduled_at', $data)) {
                $locked->scheduled_at = $data['scheduled_at'];
            }
            if (array_key_exists('notes', $data)) {
                $locked->notes = $data['notes'];
            }

            $locked->organization_unit_id = $unit?->id;
            $locked->chairperson_employee_id = $chair?->id;
            $locked->secretary_employee_id = $secretary?->id;
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::MEETING_UPDATED, [
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
