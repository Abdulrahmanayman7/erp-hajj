<?php

namespace App\Modules\Meetings\Support;

use App\Modules\Employees\Enums\EmployeeStatus;
use App\Modules\Employees\Models\Employee;
use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Exceptions\MeetingDomainException;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Models\MeetingAgendaItem;
use App\Modules\Meetings\Models\MeetingStatusTransition;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;

final class MeetingReferenceValidator
{
    public function resolveAssignableEmployee(?int $employeeId, ?int $currentEmployeeId = null): ?Employee
    {
        if ($employeeId === null) {
            return null;
        }

        $employee = Employee::query()->whereKey($employeeId)->first();

        if ($employee === null) {
            throw MeetingDomainException::employeeInvalid();
        }

        $keepingSame = $currentEmployeeId !== null && (int) $employee->id === (int) $currentEmployeeId;

        if (! $keepingSame && $employee->status !== EmployeeStatus::Active) {
            throw MeetingDomainException::employeeInvalid();
        }

        return $employee;
    }

    public function resolveAssignableOrganizationUnit(?int $unitId, ?int $currentUnitId = null): ?OrganizationUnit
    {
        if ($unitId === null) {
            return null;
        }

        $unit = OrganizationUnit::query()->whereKey($unitId)->first();

        if ($unit === null) {
            throw MeetingDomainException::organizationInvalid();
        }

        $keepingSame = $currentUnitId !== null && (int) $unit->id === (int) $currentUnitId;

        if (! $keepingSame && $unit->status !== OrganizationUnitStatus::Active) {
            throw MeetingDomainException::organizationInvalid();
        }

        return $unit;
    }

    public function assertAgendaItemBelongsToMeeting(?int $agendaItemId, Meeting $meeting): ?MeetingAgendaItem
    {
        if ($agendaItemId === null) {
            return null;
        }

        $item = MeetingAgendaItem::query()
            ->whereKey($agendaItemId)
            ->where('meeting_id', $meeting->id)
            ->first();

        if ($item === null) {
            throw MeetingDomainException::agendaItemInvalid();
        }

        return $item;
    }

    public function assertMutable(Meeting $meeting): void
    {
        if (! $meeting->isMutable()) {
            throw MeetingDomainException::notEditable();
        }
    }

    public function assertDeletableDraft(Meeting $meeting): void
    {
        if ($meeting->status !== MeetingStatus::Draft) {
            throw MeetingDomainException::deleteForbidden();
        }

        $nonCreateCount = MeetingStatusTransition::query()
            ->where('meeting_id', $meeting->id)
            ->where(function ($q): void {
                $q->whereNotNull('from_status')
                    ->orWhere('to_status', '!=', MeetingStatus::Draft->value);
            })
            ->count();

        if ($nonCreateCount > 0) {
            throw MeetingDomainException::deleteForbidden();
        }

        $total = MeetingStatusTransition::query()->where('meeting_id', $meeting->id)->count();

        if ($total > 1) {
            throw MeetingDomainException::deleteForbidden();
        }
    }
}
