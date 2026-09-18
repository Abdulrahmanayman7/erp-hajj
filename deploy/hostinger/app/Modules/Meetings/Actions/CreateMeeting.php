<?php

namespace App\Modules\Meetings\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Meetings\Enums\MeetingLocationType;
use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Support\MeetingNumberGenerator;
use App\Modules\Meetings\Support\MeetingReferenceValidator;
use App\Modules\Meetings\Support\MeetingTransitionRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateMeeting
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MeetingNumberGenerator $numbers,
        private readonly MeetingReferenceValidator $references,
        private readonly MeetingTransitionRecorder $transitions,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): Meeting
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $data, $request, $tenant): Meeting {
            $unit = $this->references->resolveAssignableOrganizationUnit(
                array_key_exists('organization_unit_id', $data) && $data['organization_unit_id'] !== null
                    ? (int) $data['organization_unit_id']
                    : null,
            );
            $chair = $this->references->resolveAssignableEmployee(
                array_key_exists('chairperson_employee_id', $data) && $data['chairperson_employee_id'] !== null
                    ? (int) $data['chairperson_employee_id']
                    : null,
            );
            $secretary = $this->references->resolveAssignableEmployee(
                array_key_exists('secretary_employee_id', $data) && $data['secretary_employee_id'] !== null
                    ? (int) $data['secretary_employee_id']
                    : null,
            );

            $locationType = $data['location_type'] ?? MeetingLocationType::Physical->value;

            $meeting = new Meeting([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => MeetingStatus::Draft,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'location_type' => MeetingLocationType::from($locationType),
                'location_text' => $data['location_text'] ?? null,
                'meeting_link' => $data['meeting_link'] ?? null,
                'organization_unit_id' => $unit?->id,
                'chairperson_employee_id' => $chair?->id,
                'secretary_employee_id' => $secretary?->id,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            $meeting->meeting_number = $this->numbers->next();
            $meeting->save();

            $this->transitions->record($meeting, null, MeetingStatus::Draft, $actor, null, $request);

            $this->security->record(AuthorizationSecurityEvent::MEETING_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'meeting_id' => $meeting->id,
                'meeting_number' => $meeting->meeting_number,
                'status' => $meeting->status->value,
            ], $request);

            return $meeting->load([
                'organizationUnit',
                'chairperson',
                'secretary',
                'createdBy',
                'statusTransitions.actor',
            ]);
        });
    }
}
