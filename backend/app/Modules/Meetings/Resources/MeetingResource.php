<?php

namespace App\Modules\Meetings\Resources;

use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingAgendaItem;
use App\Modules\Meetings\Models\MeetingAttendee;
use App\Modules\Meetings\Models\MeetingRecommendation;
use App\Modules\Meetings\Models\MeetingStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Meeting
 */
class MeetingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Meeting $meeting */
        $meeting = $this->resource;

        $payload = [
            'id' => $meeting->id,
            'meeting_number' => $meeting->meeting_number,
            'title' => $meeting->title,
            'description' => $meeting->description,
            'status' => $meeting->status->value,
            'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
            'started_at' => $meeting->started_at?->toIso8601String(),
            'ended_at' => $meeting->ended_at?->toIso8601String(),
            'location_type' => $meeting->location_type->value,
            'location_text' => $meeting->location_text,
            'meeting_link' => $meeting->meeting_link,
            'notes' => $meeting->notes,
            'is_upcoming' => $meeting->isUpcoming(),
            'organization_unit' => $this->organizationUnitPayload($meeting),
            'chairperson' => $this->employeeSummary($meeting->relationLoaded('chairperson') ? $meeting->chairperson : null),
            'secretary' => $this->employeeSummary($meeting->relationLoaded('secretary') ? $meeting->secretary : null),
            'created_by' => $this->createdByPayload($meeting),
            'created_at' => $meeting->created_at?->toIso8601String(),
            'updated_at' => $meeting->updated_at?->toIso8601String(),
        ];

        if (array_key_exists('attendees_count', $meeting->getAttributes())) {
            $payload['attendee_count'] = (int) $meeting->attendees_count;
        }

        // Include minutes on show (when nested relations loaded) and always when minutes_body was touched.
        if ($meeting->relationLoaded('attendees') || $meeting->relationLoaded('agendaItems') || $meeting->relationLoaded('recommendations') || $meeting->relationLoaded('statusTransitions')) {
            $payload['minutes_body'] = $meeting->minutes_body;
        }

        if ($meeting->relationLoaded('attendees')) {
            $payload['attendees'] = $meeting->attendees
                ->map(fn (MeetingAttendee $row): array => $this->attendeePayload($row))
                ->values()
                ->all();
        }

        if ($meeting->relationLoaded('agendaItems')) {
            $payload['agenda_items'] = $meeting->agendaItems
                ->map(fn (MeetingAgendaItem $row): array => $this->agendaItemPayload($row))
                ->values()
                ->all();
        }

        if ($meeting->relationLoaded('recommendations')) {
            $payload['recommendations'] = $meeting->recommendations
                ->map(fn (MeetingRecommendation $row): array => $this->recommendationPayload($row))
                ->values()
                ->all();
        }

        if ($meeting->relationLoaded('statusTransitions')) {
            $payload['transitions'] = $meeting->statusTransitions
                ->map(fn (MeetingStatusTransition $row): array => $this->transitionPayload($row))
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * @return array{id: int, name: string, code: string}|null
     */
    private function organizationUnitPayload(Meeting $meeting): ?array
    {
        if (! $meeting->relationLoaded('organizationUnit') || $meeting->organizationUnit === null) {
            return null;
        }

        return [
            'id' => $meeting->organizationUnit->id,
            'name' => $meeting->organizationUnit->name,
            'code' => $meeting->organizationUnit->code,
        ];
    }

    /**
     * @return array{id: int, employee_number: string, full_name: string}|null
     */
    private function employeeSummary(mixed $employee): ?array
    {
        if ($employee === null) {
            return null;
        }

        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->full_name,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function createdByPayload(Meeting $meeting): ?array
    {
        if (! $meeting->relationLoaded('createdBy') || $meeting->createdBy === null) {
            return null;
        }

        return [
            'id' => $meeting->createdBy->id,
            'name' => $meeting->createdBy->name,
        ];
    }

    /**
     * @return array{id: int, employee: array{id: int, employee_number: string, full_name: string}|null, attendance_status: string}
     */
    private function attendeePayload(MeetingAttendee $row): array
    {
        return [
            'id' => $row->id,
            'employee' => $this->employeeSummary($row->relationLoaded('employee') ? $row->employee : null),
            'attendance_status' => $row->attendance_status->value,
        ];
    }

    /**
     * @return array{id: int, title: string, description: string|null, sort_order: int}
     */
    private function agendaItemPayload(MeetingAgendaItem $row): array
    {
        return [
            'id' => $row->id,
            'title' => $row->title,
            'description' => $row->description,
            'sort_order' => $row->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recommendationPayload(MeetingRecommendation $row): array
    {
        return [
            'id' => $row->id,
            'title' => $row->title,
            'description' => $row->description,
            'status' => $row->status->value,
            'sort_order' => $row->sort_order,
            'agenda_item_id' => $row->agenda_item_id,
            'owner' => $this->employeeSummary($row->relationLoaded('owner') ? $row->owner : null),
            'created_by' => ($row->relationLoaded('createdBy') && $row->createdBy !== null)
                ? ['id' => $row->createdBy->id, 'name' => $row->createdBy->name]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transitionPayload(MeetingStatusTransition $row): array
    {
        $actor = null;
        if ($row->relationLoaded('actor') && $row->actor !== null) {
            $actor = [
                'id' => $row->actor->id,
                'name' => $row->actor->name,
            ];
        }

        return [
            'id' => $row->id,
            'from_status' => $row->from_status?->value,
            'to_status' => $row->to_status instanceof \BackedEnum
                ? $row->to_status->value
                : (string) $row->to_status,
            'comment' => $row->comment,
            'actor' => $actor,
            'correlation_id' => $row->correlation_id,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
